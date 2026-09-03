<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\FinancialTransaction;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
 use App\Services\PaymentService;
use Throwable;
use App\Services\PDFService;

class PaymentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private PDFService $pdfService
    ) {}
 
    // ═══ AJOUTER UN VERSEMENT ═══
    public function store(Request $request, int $registrationId)
    {
        $user = $request->user();
        $registration = Registration::with('student')->findOrFail($registrationId);

        // Vérification droits campus
        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        $validated = $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_date' => 'nullable|date',
            'reference'    => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            // 1. Création du Payment (immutable)
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

            // 2. Transaction financière associée
            FinancialTransaction::create([
                'registration_id' => $registration->id,
                'campus_id'       => $registration->campus_id,
                'student_id'      => $registration->student_id,
                'type'            => 'income',
                'category'        => 'tuition', // Versements ultérieurs = tuition
                'amount'          => $validated['amount'],
                'description'     => 'Versement scolarité - ' . $registration->student->first_name . ' ' . $registration->student->last_name,
                'reference'       => $payment->reference,
                'created_by'      => $user->id,
            ]);

            // 3. PaymentObserver va automatiquement mettre à jour la Scolarity

            // 4. LOG
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

    // ═══ ANNULER UN VERSEMENT (Soft Delete) ═══
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $payment = Payment::with('registration.student')->findOrFail($id);

        if (in_array($user->role, ['admin_campus', 'secretary']) && $payment->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        DB::beginTransaction();
        try {
            $studentName = $payment->registration->student->first_name . ' ' . $payment->registration->student->last_name;
            
            // Log AVANT suppression
            $this->activityLogService->log(
                action: 'deleted',
                targetType: 'payment',
                targetId: $payment->id,
                targetName: $payment->reference,
                oldData: $payment->toArray(),
                changes: 'Annulation du paiement de ' . number_format($payment->amount, 0, ',', ' ') . ' FCFA pour ' . $studentName,
                campusId: $payment->campus_id
            );

            // Soft delete → PaymentObserver mettra à jour la Scolarity
            $payment->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('PaymentController@destroy', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'annulation'], 500);
        }

        return response()->json(['message' => 'Versement annulé avec succès']);
    }

        // ═══ LISTE DES PAIEMENTS RÉCENTS (avec filtres) ═══
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Payment::with(['registration.student', 'registration.formation', 'registration.campus'])
            ->orderBy('created_at', 'desc');

        // Filtre Campus (Super/Global voient tout, autres voient le leur)
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        } elseif ($request->has('campus_id')) {
            $query->where('campus_id', $request->campus_id);
        }

        // Recherche par nom ou matricule
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('registration.student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->paginate(15)
        ]);
    }

    // ═══ RECHERCHE RAPIDE POUR LE MODAL (Autocomplete) ═══
    public function searchStudents(Request $request)
    {
        $query = $request->get('q', '');
        $user = $request->user();

        $registrations = Registration::with(['student', 'formation', 'scolarity'])
            ->whereDoesntHave('student', function ($q) { $q->whereNotNull('deleted_at'); }) // Exclure les supprimés
            ->where(function ($q) use ($query) {
                $q->whereHas('student', function ($sq) use ($query) {
                    $sq->where('registration_number', 'like', "%{$query}%")
                       ->orWhere('first_name', 'like', "%{$query}%")
                       ->orWhere('last_name', 'like', "%{$query}%");
                });
            });

        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $registrations->where('campus_id', $user->campus_id);
        }

        return response()->json([
            'data' => $registrations->limit(10)->get()->map(function ($reg) {
                return [
                    'id' => $reg->id,
                    'matricule' => $reg->student->registration_number,
                    'name' => $reg->student->first_name . ' ' . $reg->student->last_name,
                    'formation' => $reg->formation->name,
                    'tuition_fees' => $reg->formation->tuition_fees,
                    'amount_paid' => $reg->amount_paid, // Accessor
                    'balance' => $reg->balance,         // Accessor
                ];
            })
        ]);
    }

    // ═══ GÉNÉRER LE REÇU PDF ═══
        public function generateReceipt(Request $request, int $id)
    {
        $user = $request->user();
        $payment = Payment::with(['registration.student', 'registration.formation', 'registration.campus', 'registration.academicYear'])->findOrFail($id);

        if (in_array($user->role, ['admin_campus', 'secretary']) && $payment->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $qrUrl = generatePaymentQRData($payment, $payment->registration->student, $payment->registration);
        $qrCodeRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->errorCorrection('H')->generate($qrUrl);
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeRaw);

        // ✅ PASSE L'UTILISATEUR EN 3ÈME ARGUMENT
        $pdfContent = $this->pdfService->generatePaymentReceipt($payment, $qrCodeBase64, $user);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="recu-' . $payment->reference . '.pdf"');
    }
}