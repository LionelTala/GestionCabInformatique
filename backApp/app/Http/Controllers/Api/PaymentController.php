<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\FinancialTransaction;
use App\Services\ActivityLogService;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private PDFService $pdfService
    ) {}

    // ═══════════════════════════════════════════════════════════
    // ═══ LISTE DES PAIEMENTS ═══
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();

        $query = Payment::with([
            'registration.student:id,first_name,last_name,registration_number',
            'registration.formation:id,name,abbreviation',
            'registration.campus:id,name',
            'registration.academicYear:id,label',
        ])->orderBy('payment_date', 'desc');

        // ─── 1. SCOPE PAR RÔLE ────────────────────────────────
        // ✅ Secrétaire ET admin_campus : tout leur campus
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        }
        // ✅ Admin global / super : tout, filtrable par campus
        elseif (in_array($user->role, ['super_admin', 'admin_global'])) {
            if ($request->filled('campus_id')) {
                $query->where('campus_id', $request->integer('campus_id'));
            }
        }

        // ─── 2. FILTRE PÉRIODE ────────────────────────────────
        $period = $request->get('period', 'today');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $request);

        if ($dateFrom) $query->whereDate('payment_date', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('payment_date', '<=', $dateTo);

        // ─── 3. FILTRES ───────────────────────────────────────
        if ($request->filled('formation_id')) {
            $query->whereHas('registration', function ($q) use ($request) {
                $q->where('formation_id', $request->integer('formation_id'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('registration.student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        // ─── 4. STATS DE LA PÉRIODE ───────────────────────────
        $totalAmount = (clone $query)->sum('amount');
        $totalCount  = (clone $query)->count();

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 15)),
            'meta' => [
                'period'       => $period,
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
                'total_amount' => (float) $totalAmount,
                'total_count'  => $totalCount,
            ],
        ]);
    }

    private function resolvePeriod(string $period, Request $request): array
    {
        $today = now()->toDateString();

        return match ($period) {
            'today'  => [$today, $today],
            'week'   => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month'  => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'   => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom' => [
                $request->filled('date_from') ? $request->date('date_from')->toDateString() : null,
                $request->filled('date_to')   ? $request->date('date_to')->toDateString()   : null,
            ],
            'all'    => [null, null],
            default  => [$today, $today],
        };
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ AJOUTER UN VERSEMENT ═══
    // ═══════════════════════════════════════════════════════════
    public function store(Request $request, int $registrationId)
    {
        $this->authorize('create', Payment::class);

        $user = $request->user();
        $registration = Registration::with(['student', 'scolarity', 'formation'])->findOrFail($registrationId);

        if (in_array($user->role, ['admin_campus', 'secretary'])
            && (int) $registration->campus_id !== (int) $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        $validated = $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_date' => 'nullable|date',
            'reference'    => 'nullable|string|max:100',
        ]);

        $scolarity = $registration->scolarity;
        if ($scolarity) {
            $remaining = (float) $scolarity->balance;
            if ((float) $validated['amount'] > $remaining) {
                return response()->json([
                    'message' => "Le montant dépasse le solde restant dû ("
                        . number_format($remaining, 0, ',', ' ') . " FCFA).",
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'registration_id' => $registration->id,
                'campus_id'       => $registration->campus_id,
                'student_id'      => $registration->student_id,
                'amount'          => $validated['amount'],
                'payment_date'    => $validated['payment_date'] ?? now(),
                'reference'       => $validated['reference'] ?? 'PAY-' . now()->format('YmdHis'),
                'status'          => 'confirmed',
                'created_by'      => $user->id,
            ]);

            FinancialTransaction::create([
                'registration_id' => $registration->id,
                'campus_id'       => $registration->campus_id,
                'student_id'      => $registration->student_id,
                'type'            => 'income',
                'category'        => 'tuition',
                'amount'          => $validated['amount'],
                'description'     => 'Versement scolarité - ' . $registration->student->first_name . ' ' . $registration->student->last_name,
                'reference'       => $payment->reference,
                'created_by'      => $user->id,
            ]);

            $studentName = $registration->student->first_name . ' ' . $registration->student->last_name;
            $this->activityLogService->log(
                action: 'created',
                targetType: 'payment',
                targetId: $payment->id,
                targetName: $payment->reference,
                newData: $payment->toArray(),
                changes: 'Nouveau versement de ' . number_format($validated['amount'], 0, ',', ' ') . ' FCFA pour ' . $studentName,
                campusId: $registration->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('PaymentController@store', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du paiement'], 500);
        }

        return response()->json([
            'message' => 'Versement enregistré avec succès',
            'data'    => $payment->load('registration.student'),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ ANNULER UN VERSEMENT ═══
    // ═══════════════════════════════════════════════════════════
    public function destroy(Request $request, int $id)
    {
        $payment = Payment::with(['registration.student', 'registration.scolarity'])->findOrFail($id);

        // ✅ Policy : secrétaire ne peut supprimer QUE ses propres paiements
        $this->authorize('delete', $payment);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $studentName = $payment->registration->student->first_name . ' ' . $payment->registration->student->last_name;
            $amount      = (float) $payment->amount;

            $this->activityLogService->log(
                action: 'deleted',
                targetType: 'payment',
                targetId: $payment->id,
                targetName: $payment->reference,
                oldData: $payment->toArray(),
                changes: 'Annulation du paiement de ' . number_format($amount, 0, ',', ' ') . ' FCFA pour ' . $studentName,
                campusId: $payment->campus_id
            );

            FinancialTransaction::create([
                'registration_id' => $payment->registration_id,
                'campus_id'       => $payment->campus_id,
                'student_id'      => $payment->student_id,
                'type'            => 'expense',
                'category'        => 'tuition_refund',
                'amount'          => $amount,
                'description'     => 'Annulation du paiement ' . $payment->reference
                                    . ' - ' . $studentName,
                'reference'       => 'CANCEL-' . $payment->reference,
                'created_by'      => $user->id,
            ]);

            $payment->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('PaymentController@destroy', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'annulation'], 500);
        }

        return response()->json(['message' => 'Versement annulé avec succès']);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ RECHERCHE RAPIDE ═══
    // ═══════════════════════════════════════════════════════════
    public function searchStudents(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $query = $request->get('q', '');
        $user = $request->user();

        $registrations = Registration::with(['student', 'formation', 'scolarity'])
            ->whereDoesntHave('student', function ($q) { $q->whereNotNull('deleted_at'); })
            ->where(function ($q) use ($query) {
                $q->whereHas('student', function ($sq) use ($query) {
                    $sq->where('registration_number', 'like', "%{$query}%")
                       ->orWhere('first_name', 'like', "%{$query}%")
                       ->orWhere('last_name', 'like', "%{$query}%");
                });
            });

        // ✅ Secrétaire + admin_campus : leur campus
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $registrations->where('campus_id', $user->campus_id);
        }

        return response()->json([
            'data' => $registrations->limit(10)->get()->map(function ($reg) {
                return [
                    'id'           => $reg->id,
                    'matricule'    => $reg->student->registration_number,
                    'name'         => $reg->student->first_name . ' ' . $reg->student->last_name,
                    'formation'    => $reg->formation->name,
                    'tuition_fees' => $reg->scolarity?->tuition_fees ?? $reg->formation->tuition_fees,
                    'amount_paid'  => $reg->scolarity?->amount_paid ?? 0,
                    'balance'      => $reg->scolarity?->balance ?? 0,
                ];
            })
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ REÇU PDF ═══
    // ═══════════════════════════════════════════════════════════
    public function generateReceipt(Request $request, int $id)
    {
        $payment = Payment::with([
            'registration.student',
            'registration.formation',
            'registration.campus',
            'registration.academicYear'
        ])->findOrFail($id);

        // ✅ Policy : secrétaire peut télécharger N'IMPORTE QUEL reçu de son campus
        $this->authorize('view', $payment);

        $user = $request->user();

        $qrUrl = generatePaymentQRData($payment, $payment->registration->student, $payment->registration);
        $qrCodeRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->errorCorrection('H')->generate($qrUrl);
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeRaw);

        $pdfContent = $this->pdfService->generatePaymentReceipt($payment, $qrCodeBase64, $user);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="recu-' . $payment->reference . '.pdf"');
    }
}