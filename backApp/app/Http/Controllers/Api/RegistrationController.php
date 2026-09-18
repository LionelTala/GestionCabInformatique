<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Scolarity;
use App\Models\Registration;
use App\Models\FinancialTransaction;
use App\Models\CashMovement;
use App\Models\Formation;
use App\Services\ActivityLogService;
use App\Services\PDFStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrationController extends Controller
{
    public function __construct(
        private \App\Services\QRCodeService $qrCodeService,
        private \App\Services\PDFService $pdfService,
        private ActivityLogService $activityLogService,
        private PDFStorageService $pdfStorage,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // ═══ LISTE ═══
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', Registration::class);

        $user = $request->user();

        $query = Registration::with([
            'student:id,first_name,last_name,registration_number,email,phone,residence',
            'formation:id,name,abbreviation,tuition_fees',
            'campus:id,name,city',
            'academicYear:id,label',
            'payments',
            'scolarity:id,registration_id,tuition_fees,amount_paid,balance,status'
        ]);

        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        }

        foreach (['campus_id', 'formation_id', 'academic_year_id', 'status'] as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->$filter);
            }
        }

        return response()->json([
            'data' => $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 15)),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ DÉTAILS ═══
    // ═══════════════════════════════════════════════════════════
    public function show(Request $request, int $id)
    {
        $registration = Registration::with([
            'student', 'formation', 'campus', 'academicYear', 'payments', 'scolarity'
        ])->findOrFail($id);

        $this->authorize('view', $registration);

        return response()->json(['data' => $registration]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ CRÉER ═══
    // ═══════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $this->authorize('create', Registration::class);

        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'residence' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',

            'highest_diploma' => 'nullable|string|max:255',
            'diploma_year' => 'nullable|integer|min:1950|max:2030',
            'languages' => 'nullable|array',
            'languages.*' => 'in:francais,anglais,autre',

            'parent_name' => 'nullable|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'formation_id' => 'required|exists:formations,id',
            'campus_id' => 'required|exists:campuses,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'initial_payment' => 'nullable|numeric|min:0',

            'custom_tuition' => 'nullable|numeric|min:0',

            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $campusId = in_array($user->role, ['admin_campus', 'secretary'])
            ? $user->campus_id
            : $validated['campus_id'];

        $formation = Formation::findOrFail($validated['formation_id']);
        $initialPayment = $validated['initial_payment'] ?? 0;

        $finalTuition = !empty($validated['custom_tuition'])
            ? (float) $validated['custom_tuition']
            : (float) $formation->tuition_fees;

        $year = now()->format('y');
        $abbreviation = $formation->abbreviation;
        do {
            $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $registrationNumber = $year . $abbreviation . $random;
        } while (Student::where('registration_number', $registrationNumber)->exists());

        DB::beginTransaction();
        try {
            $languagesJson = null;
            if (!empty($validated['languages']) && is_array($validated['languages'])) {
                $languagesJson = json_encode($validated['languages']);
            }

            $student = Student::create([
                'campus_id' => $campusId,
                'registration_number' => $registrationNumber,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'formation_id' => $validated['formation_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'residence' => $validated['residence'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'place_of_birth' => $validated['place_of_birth'] ?? null,
                'highest_diploma' => $validated['highest_diploma'] ?? null,
                'diploma_year' => $validated['diploma_year'] ?? null,
                'languages' => $languagesJson,
                'parent_name' => $validated['parent_name'] ?? null,
                'parent_phone' => $validated['parent_phone'] ?? null,
                'created_by' => $user->id,
            ]);

            if ($request->hasFile('photo')) {
                $student->photo = $request->file('photo')->storeAs('students', $registrationNumber . '.jpg', 'private');
                $student->save();
            }

            $registration = Registration::create([
                'student_id' => $student->id,
                'campus_id' => $campusId,
                'formation_id' => $validated['formation_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'initial_payment' => $initialPayment,
                'custom_tuition' => $finalTuition,
                'status' => 'confirmed',
                'created_by' => $user->id,
            ]);

            $amountPaid = $initialPayment;
            $balance = max(0, $finalTuition - $amountPaid);
            $status = $amountPaid <= 0 ? 'unpaid' : ($amountPaid >= $finalTuition ? 'paid' : 'partial');

            Scolarity::create([
                'registration_id' => $registration->id,
                'student_id' => $student->id,
                'campus_id' => $campusId,
                'tuition_fees' => $finalTuition,
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'status' => $status,
            ]);

            if ($initialPayment > 0) {
                FinancialTransaction::create([
                    'registration_id' => $registration->id,
                    'campus_id' => $campusId,
                    'student_id' => $student->id,
                    'type' => 'income',
                    'category' => 'registration',
                    'amount' => $initialPayment,
                    'description' => 'Versement initial - ' . $student->first_name . ' ' . $student->last_name,
                    'reference' => 'INIT-' . now()->format('Ymd') . '-' . str_pad($registration->id, 4, '0', STR_PAD_LEFT),
                    'created_by' => $user->id,
                ]);
            }

            $qrData = [
                'matricule'         => $student->registration_number,
                'name'              => $student->first_name . ' ' . $student->last_name,
                'formation'         => $formation->name,
                'formation_code'    => $formation->abbreviation,
                'campus'            => $registration->campus?->name ?? 'CAB Informatique',
                'registration_id'   => $registration->id,
                'registration_date' => $registration->created_at->format('d/m/Y'),
                'academic_year'     => $registration->academicYear->label ?? (date('Y') . '-' . (date('Y') + 1)),
                'tuition_fees'      => $finalTuition,
                'amount_paid'       => $amountPaid,
                'financial_status'  => $status,
            ];

            $signature = generateDocumentSignature($qrData);
            $registration->qr_code_hash = $signature;
            $registration->save();

            $logChanges = 'Nouvelle inscription : ' . $student->first_name . ' ' . $student->last_name . ' — ' . $formation->name;
            if ($finalTuition < $formation->tuition_fees) {
                $logChanges .= ' (PROMO appliquée : ' . number_format($finalTuition, 0, ',', ' ') . ' FCFA au lieu de ' . number_format($formation->tuition_fees, 0, ',', ' ') . ' FCFA)';
            }
            if ($initialPayment > 0) {
                $logChanges .= ' (Versement initial : ' . number_format($initialPayment, 0, ',', ' ') . ' FCFA)';
            }

            $this->activityLogService->log(
                action: 'created',
                targetType: 'registration',
                targetId: $registration->id,
                targetName: $student->first_name . ' ' . $student->last_name,
                newData: $registration->toArray(),
                changes: $logChanges,
                campusId: $campusId
            );

            // ═══════════════════════════════════════════════════════
            // ✅ GÉNÉRATION IMMÉDIATE DE LA FICHE PDF
            // Prête avant même que l'utilisateur ne clique sur
            // "télécharger" : le téléchargement sera donc instantané.
            // ═══════════════════════════════════════════════════════
            $registration->load(['student', 'formation', 'campus', 'academicYear', 'scolarity']);
            $this->generateAndStoreForm($registration);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('store() - Erreur création inscription', ['message' => $e->getMessage()]);
            return response()->json(['message' => "Erreur lors de la création de l'inscription : " . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Inscription réussie',
            'data' => [
                'student' => $student,
                'registration' => $registration->load(['student', 'formation', 'campus', 'academicYear', 'scolarity'])
            ],
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ MODIFIER ═══
    // ═══════════════════════════════════════════════════════════
    public function update(Request $request, int $id)
    {
        $registration = Registration::with(['student', 'payments', 'scolarity'])->findOrFail($id);

        $this->authorize('update', $registration);

        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'residence' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',

            'highest_diploma' => 'nullable|string|max:255',
            'diploma_year' => 'nullable|integer|min:1950|max:2030',
            'languages' => 'nullable|array',
            'languages.*' => 'in:francais,anglais,autre',

            'parent_name' => 'nullable|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'formation_id' => 'sometimes|exists:formations,id',
            'academic_year_id' => 'sometimes|exists:academic_years,id',

            'custom_tuition' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $oldData = $registration->only(['formation_id', 'academic_year_id', 'custom_tuition']);
            $oldData = array_merge($oldData, $registration->student->only([
                'first_name', 'last_name', 'phone', 'email', 'residence',
                'highest_diploma', 'diploma_year', 'languages'
            ]));

            $student = $registration->student;

            $studentData = array_intersect_key($validated, array_flip([
                'first_name', 'last_name', 'email', 'phone', 'residence', 'date_of_birth','place_of_birth',
                'highest_diploma', 'diploma_year', 'languages', 'parent_name', 'parent_phone'
            ]));

            if (isset($studentData['languages']) && is_array($studentData['languages'])) {
                $studentData['languages'] = json_encode($studentData['languages']);
            }

            $student->update($studentData);

            $newFormationId = $validated['formation_id'] ?? $registration->formation_id;
            $formation = Formation::find($newFormationId);

            $registrationData = array_intersect_key($validated, array_flip(['formation_id', 'academic_year_id']));
            $registrationData['custom_tuition'] = !empty($validated['custom_tuition'])
                ? (float) $validated['custom_tuition']
                : (float) $formation->tuition_fees;

            $registration->update($registrationData);

            $scolarity = $registration->scolarity;
            if ($scolarity) {
                $newTuition = (float) $registration->custom_tuition;
                $newBalance = max(0, $newTuition - $scolarity->amount_paid);
                $newStatus = $scolarity->amount_paid <= 0
                    ? 'unpaid'
                    : ($scolarity->amount_paid >= $newTuition ? 'paid' : 'partial');

                $scolarity->update([
                    'tuition_fees' => $newTuition,
                    'balance' => $newBalance,
                    'status' => $newStatus,
                ]);
            }

            // ✅ Invalider la fiche d'inscription (les données ont changé)
            // Elle sera régénérée automatiquement au prochain téléchargement
            // (voir downloadForm ci-dessous).
            $this->pdfStorage->deleteRegistrationPDF($registration);
            $registration->update(['pdf_path' => null, 'pdf_generated_at' => null]);

            // ✅ Invalider tous les reçus liés (le solde a changé, les infos étudiant aussi)
            foreach ($registration->payments as $payment) {
                $this->pdfStorage->deleteReceiptPDF($payment);
                $payment->update(['receipt_path' => null, 'receipt_generated_at' => null]);
            }

            $this->activityLogService->log(
                action: 'updated',
                targetType: 'registration',
                targetId: $registration->id,
                targetName: $student->first_name . ' ' . $student->last_name,
                oldData: $oldData,
                newData: $registration->fresh()->only(['formation_id', 'academic_year_id', 'custom_tuition'])
                    + $student->fresh()->only([
                        'first_name', 'last_name', 'phone', 'email', 'residence',
                        'highest_diploma', 'diploma_year', 'languages'
                    ]),
                changes: 'Modification des informations de l\'étudiant ou de la scolarité',
                campusId: $registration->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('update() - Erreur', ['message' => $e->getMessage()]);
            return response()->json(['message' => "Erreur lors de la modification"], 500);
        }

        return response()->json([
            'message' => 'Modifié avec succès',
            'data' => $registration->load(['student', 'formation', 'campus', 'academicYear', 'payments', 'scolarity'])
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ SUPPRIMER ═══
    // ═══════════════════════════════════════════════════════════
    public function destroy(Request $request, int $id)
    {
        $registration = Registration::with([
            'student',
            'payments',
            'scolarity',
        ])->findOrFail($id);

        $this->authorize('delete', $registration);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $student        = $registration->student;
            $studentName    = $student->first_name . ' ' . $student->last_name;
            $matricule      = $student->registration_number;

            $initialPayment = (float) $registration->initial_payment;
            $payments       = $registration->payments()->where('status', 'confirmed')->get();
            $paymentsSum    = (float) $payments->sum('amount');
            $totalToRefund  = $initialPayment + $paymentsSum;

            $changesLog = 'Suppression DÉFINITIVE de l\'inscription de ' . $studentName
                        . ' (Matricule: ' . $matricule . ')';
            if ($totalToRefund > 0) {
                $changesLog .= ' — Contre-écriture comptable de '
                    . number_format($totalToRefund, 0, ',', ' ')
                    . ' FCFA';
            }

            $this->activityLogService->log(
                action: 'deleted',
                targetType: 'registration',
                targetId: $registration->id,
                targetName: $studentName,
                oldData: array_merge(
                    $registration->toArray(),
                    [
                        'student'         => $student->toArray(),
                        'payments'        => $payments->toArray(),
                        'scolarity'       => $registration->scolarity?->toArray(),
                        'total_refunded'  => $totalToRefund,
                    ]
                ),
                changes: $changesLog,
                campusId: $registration->campus_id
            );

            if ($initialPayment > 0) {
                FinancialTransaction::create([
                    'registration_id' => $registration->id,
                    'campus_id'       => $registration->campus_id,
                    'student_id'      => $registration->student_id,
                    'type'            => 'expense',
                    'category'        => 'registration_cancel',
                    'amount'          => $initialPayment,
                    'description'     => 'Suppression inscription — versement initial — ' . $studentName,
                    'reference'       => 'DEL-INIT-' . $registration->id . '-' . now()->format('YmdHis'),
                    'created_by'      => $user->id,
                ]);
            }

            foreach ($payments as $payment) {
                FinancialTransaction::create([
                    'registration_id' => $registration->id,
                    'campus_id'       => $registration->campus_id,
                    'student_id'      => $registration->student_id,
                    'type'            => 'expense',
                    'category'        => 'payment_cancel',
                    'amount'          => (float) $payment->amount,
                    'description'     => 'Suppression inscription — annulation paiement '
                                        . $payment->reference . ' — ' . $studentName,
                    'reference'       => 'DEL-' . $payment->reference,
                    'created_by'      => $user->id,
                ]);

                // ✅ Supprimer le PDF du reçu du disque
                $this->pdfStorage->deleteReceiptPDF($payment);
            }

            // ✅ Supprimer la fiche d'inscription du disque
            $this->pdfStorage->deleteRegistrationPDF($registration);

            // ✅ Suppression définitive en BD
            $registration->payments()->forceDelete();

            if ($registration->scolarity) {
                $registration->scolarity()->forceDelete();
            }

            $registration->forceDelete();
            $student->forceDelete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('destroy() - Erreur', [
                'registration_id' => $id,
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => "Erreur lors de la suppression : " . $e->getMessage()
            ], 500);
        }

        return response()->json(['message' => 'Inscription supprimée définitivement']);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ TÉLÉCHARGEMENT DE LA FICHE D'INSCRIPTION PDF ═══
    // ═══════════════════════════════════════════════════════════
    // ✅ Même principe que pour les reçus de paiement :
    //    - pdf_path renseigné => déjà généré (à la création, ou lors
    //      d'un précédent aperçu/téléchargement) => servi directement,
    //      sans charger aucune relation.
    //    - pdf_path vide (fiche invalidée par un update(), ou jamais
    //      générée pour une inscription créée avant cette optimisation)
    //      => régénération à la volée, puis stockage.
    // ═══════════════════════════════════════════════════════════
    public function downloadForm(Request $request, int $id)
    {
        $registration = Registration::findOrFail($id);

        $this->authorize('view', $registration);

        $user = $request->user();
        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        // ═══ Cas normal : la fiche existe déjà ═══
        if ($registration->pdf_path && $this->pdfStorage->exists($registration->pdf_path)) {
            return $this->streamForm($registration);
        }

        // ═══ Cas de secours : régénération à la volée ═══
        $registration->load([
            'student', 'formation', 'campus', 'academicYear', 'scolarity',
        ]);

        $this->generateAndStoreForm($registration);

        return $this->streamForm($registration);
    }

    /**
     * ✅ Aperçu PDF (affichage inline) — même logique cache/génération.
     */
    public function generateForm(Request $request, int $id)
    {
        $user = $request->user();
        $registration = Registration::findOrFail($id);

        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        if ($registration->pdf_path && $this->pdfStorage->exists($registration->pdf_path)) {
            $content = $this->pdfStorage->get($registration->pdf_path);

            return response($content)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="fiche-inscription-' . $registration->student->registration_number . '.pdf"');
        }

        $registration->load(['student', 'formation', 'campus', 'academicYear', 'scolarity']);
        $this->generateAndStoreForm($registration);

        $content = $this->pdfStorage->get($registration->pdf_path);

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="fiche-inscription-' . $registration->student->registration_number . '.pdf"');
    }

    /**
     * ✅ Génère la fiche PDF, la stocke, et met à jour pdf_path / pdf_generated_at.
     * Centralise une logique auparavant dupliquée à 3 endroits.
     * Suppose que $registration a déjà chargé : student, formation, campus, academicYear, scolarity.
     */
    private function generateAndStoreForm(Registration $registration): void
    {
        $actualTuition = $registration->scolarity?->tuition_fees ?? $registration->formation->tuition_fees;
        $actualPaid    = $registration->scolarity?->amount_paid ?? 0;
        $actualStatus  = $registration->scolarity?->status ?? 'unpaid';

        $qrData = [
            'matricule'         => $registration->student->registration_number,
            'name'              => $registration->student->last_name . ' ' . $registration->student->first_name,
            'formation'         => $registration->formation->name,
            'formation_code'    => $registration->formation->abbreviation,
            'campus'            => $registration->campus?->name ?? 'CAB Informatique',
            'registration_id'   => $registration->id,
            'registration_date' => $registration->created_at->format('d/m/Y'),
            'academic_year'     => $registration->academicYear->label ?? (date('Y') . '-' . (date('Y') + 1)),
            'tuition_fees'      => $actualTuition,
            'amount_paid'       => $actualPaid,
            'financial_status'  => $actualStatus,
        ];

        $secureQRData = generateSecureQRData('registration', $qrData);

        try {
            $qrCodeRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(100)
                ->errorCorrection('H')
                ->generate($secureQRData);
            $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeRaw);
        } catch (\Exception $e) {
            $qrCodeBase64 = null;
            Log::error('QR Code generation failed: ' . $e->getMessage());
        }

        $pdfContent = $this->pdfService->generateRegistrationForm($registration, $qrCodeBase64);

        $path = $this->pdfStorage->registrationPath($registration);
        $this->pdfStorage->store($path, $pdfContent);

        $registration->update([
            'pdf_path'         => $path,
            'pdf_generated_at' => now(),
        ]);
    }

    /**
     * ✅ Sert la fiche PDF déjà stockée sur le disque privé.
     *
     * NOTE : identique à la remarque faite sur PaymentController —
     * $this->pdfStorage->get() charge tout le fichier en mémoire.
     * Montre-moi PDFStorageService pour passer à un vrai streaming
     * via Storage::disk('private')->download(...).
     */
    private function streamForm(Registration $registration): Response
    {
        $content = $this->pdfStorage->get($registration->pdf_path);

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="fiche-inscription-' . $registration->student->registration_number . '.pdf"');
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ STATS ═══
    // ═══════════════════════════════════════════════════════════
    public function stats(Request $request, int $campusId)
    {
        $user = $request->user();

        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            if ($campusId !== (int) $user->campus_id) {
                return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
            }
        }

        $scolarityIncome  = FinancialTransaction::where('campus_id', $campusId)->where('type', 'income')->sum('amount');
        $scolarityExpense = FinancialTransaction::where('campus_id', $campusId)->where('type', 'expense')->sum('amount');
        $scolarityBalance = $scolarityIncome - $scolarityExpense;

        $cashIncome  = CashMovement::where('campus_id', $campusId)->where('type', 'income')->sum('amount');
        $cashExpense = CashMovement::where('campus_id', $campusId)->where('type', 'expense')->sum('amount');
        $cashBalance = $cashIncome - $cashExpense;

        return response()->json([
            'data' => [
                'scolarity_income'  => (float) $scolarityIncome,
                'scolarity_expense' => (float) $scolarityExpense,
                'scolarity_balance' => (float) $scolarityBalance,

                'cash_income'   => (float) $cashIncome,
                'cash_expense'  => (float) $cashExpense,
                'cash_balance'  => (float) $cashBalance,

                'total_income'  => (float) ($scolarityIncome + $cashIncome),
                'total_expense' => (float) ($scolarityExpense + $cashExpense),
                'balance'       => (float) ($scolarityBalance + $cashBalance),

                'count' => Registration::where('campus_id', $campusId)->count(),
            ],
        ]);
    }
}