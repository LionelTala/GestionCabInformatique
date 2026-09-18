<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\FinancialTransaction;
use App\Services\ActivityLogService;
use App\Services\PDFService;
use App\Services\PDFStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private PDFService $pdfService,
        private PDFStorageService $pdfStorage
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

        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        } elseif (in_array($user->role, ['super_admin', 'admin_global'])) {
            if ($request->filled('campus_id')) {
                $query->where('campus_id', $request->integer('campus_id'));
            }
        }

        $period = $request->get('period', 'today');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $request);

        if ($dateFrom) $query->whereDate('payment_date', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('payment_date', '<=', $dateTo);

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

            // ✅ Invalider la fiche d'inscription (le solde a changé)
            $this->pdfStorage->deleteRegistrationPDF($registration);
            $registration->update(['pdf_path' => null, 'pdf_generated_at' => null]);

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

            // ═══════════════════════════════════════════════════════
            // ✅ GÉNÉRATION IMMÉDIATE DU PDF
            // Le reçu est prêt AVANT que l'utilisateur ne clique sur
            // "télécharger" : le téléchargement sera donc toujours instantané.
            // ═══════════════════════════════════════════════════════
            $payment->load([
                'registration.student',
                'registration.formation',
                'registration.campus',
                'registration.academicYear',
                'registration.scolarity',
                'campus',
            ]);
            $this->generateAndStoreReceipt($payment, $user);

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

            // ✅ Supprimer le PDF du reçu du disque
            $this->pdfStorage->deleteReceiptPDF($payment);

            // ✅ Invalider la fiche d'inscription (le solde a changé)
            $registration = $payment->registration;
            $this->pdfStorage->deleteRegistrationPDF($registration);
            $registration->update(['pdf_path' => null, 'pdf_generated_at' => null]);

            // ✅ Supprimer le paiement (soft delete)
            $payment->update(['receipt_path' => null, 'receipt_generated_at' => null]);
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

        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $registrations->where('campus_id', $user->campus_id);
        }

        return response()->json([
            'data' => $registrations->limit(10)->get()->map(function ($reg) {
                return [
                    'id'           => $reg->id,
                    'matricule'    => $reg->student->registration_number,
                    'name'         => $reg->student->last_name . ' ' . $reg->student->first_name,
                    'formation'    => $reg->formation->name,
                    'tuition_fees' => $reg->scolarity?->tuition_fees ?? $reg->formation->tuition_fees,
                    'amount_paid'  => $reg->scolarity?->amount_paid ?? 0,
                    'balance'      => $reg->scolarity?->balance ?? 0,
                ];
            })
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ TÉLÉCHARGEMENT DU REÇU PDF ═══
    // ═══════════════════════════════════════════════════════════
    // ✅ Principe simplifié :
    //    - receipt_path renseigné => le PDF existe déjà (généré à la
    //      création du paiement) => on le sert directement, sans
    //      charger aucune relation.
    //    - receipt_path vide (cas de secours : ancien paiement créé
    //      avant cette optimisation, ou fichier perdu) => on régénère
    //      à la volée puis on stocke, pour que les fois suivantes
    //      retombent dans le premier cas.
    // ═══════════════════════════════════════════════════════════
    public function downloadReceipt(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);

        $this->authorize('view', $payment);

        // ═══ Cas normal : le PDF a été généré à la création du paiement ═══
        if ($payment->receipt_path && $this->pdfStorage->exists($payment->receipt_path)) {
            return $this->streamReceipt($payment);
        }

        // ═══ Cas de secours : génération à la volée ═══
        $payment->load([
            'registration.student',
            'registration.formation',
            'registration.campus',
            'registration.academicYear',
            'registration.scolarity',
            'campus',
            'createdBy:id,last_name,first_name',
        ]);

        $this->generateAndStoreReceipt($payment, $payment->createdBy);

        return $this->streamReceipt($payment);
    }

    /**
     * ✅ Génère le PDF du reçu, le stocke sur le disque privé,
     * et met à jour receipt_path / receipt_generated_at.
     * Centralise une logique auparavant dupliquée à 3 endroits.
     */
    private function generateAndStoreReceipt(Payment $payment, $user): void
    {
        $qrUrl = generatePaymentQRData($payment, $payment->registration->student, $payment->registration);

        try {
            $qrCodeRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(100)
                ->errorCorrection('H')
                ->generate($qrUrl);
            $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeRaw);
        } catch (\Exception $e) {
            $qrCodeBase64 = null;
            Log::error('QR Code generation failed: ' . $e->getMessage());
        }

        $pdfContent = $this->pdfService->generatePaymentReceipt($payment, $qrCodeBase64, $user);

        $path = $this->pdfStorage->receiptPath($payment);
        $this->pdfStorage->store($path, $pdfContent);

        $payment->update([
            'receipt_path'         => $path,
            'receipt_generated_at' => now(),
        ]);
    }

    /**
     * ✅ Sert le PDF déjà stocké sur le disque privé.
     *
     * NOTE : $this->pdfStorage->get() charge tout le fichier en mémoire
     * avant de le renvoyer. Si PDFStorageService utilise un disque LOCAL
     * (config/filesystems.php => 'private' => driver 'local'), remplace
     * idéalement cette méthode par un vrai streaming, par exemple :
     *
     *   return Storage::disk('private')->download(
     *       $payment->receipt_path,
     *       'recu-' . $payment->reference . '.pdf'
     *   );
     *
     * Storage::download() utilise en interne response()->file(), qui
     * stream le fichier directement depuis le disque sans jamais le
     * charger entièrement en mémoire PHP — plus rapide et plus léger,
     * surtout sur un hébergement mutualisé à mémoire limitée.
     * Montre-moi PDFStorageService si tu veux que je fasse ce
     * changement précisément avec ton implémentation actuelle.
     */
    private function streamReceipt(Payment $payment): Response
    {
        $content = $this->pdfStorage->get($payment->receipt_path);

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="recu-' . $payment->reference . '.pdf"');
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ APERÇU PDF (affichage inline, ex: prévisualisation) ═══
    // ═══════════════════════════════════════════════════════════
    public function generateReceipt(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);
        $this->authorize('view', $payment);

        if ($payment->receipt_path && $this->pdfStorage->exists($payment->receipt_path)) {
            $content = $this->pdfStorage->get($payment->receipt_path);

            return response($content)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="recu-' . $payment->reference . '.pdf"');
        }

        $payment->load([
            'registration.student',
            'registration.formation',
            'registration.campus',
            'registration.academicYear',
            'registration.scolarity',
            'campus',
            'createdBy:id,last_name,first_name',
        ]);

        $this->generateAndStoreReceipt($payment, $request->user());

        $content = $this->pdfStorage->get($payment->receipt_path);

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="recu-' . $payment->reference . '.pdf"');
    }
}