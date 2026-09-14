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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrationController extends Controller
{
    public function __construct(
        private \App\Services\QRCodeService $qrCodeService,
        private \App\Services\PDFService $pdfService,
        private ActivityLogService $activityLogService,
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

        // ✅ Scope par rôle (obligatoire pour les listes)
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        }

        // Filtres
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

        // ✅ Policy : vérifie l'accès au campus
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

            // Champs académiques
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

            // Montant promo
            'custom_tuition' => 'nullable|numeric|min:0',

            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // ✅ Sécurité : secrétaire / admin_campus → forcé sur leur campus
        $campusId = in_array($user->role, ['admin_campus', 'secretary'])
            ? $user->campus_id
            : $validated['campus_id'];

        $formation = Formation::findOrFail($validated['formation_id']);
        $initialPayment = $validated['initial_payment'] ?? 0;

        // ✅ Si pas de promo → custom_tuition = prix de la formation
        $finalTuition = !empty($validated['custom_tuition'])
            ? (float) $validated['custom_tuition']
            : (float) $formation->tuition_fees;

        // Génération matricule
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

            // 1. Création Étudiant
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

            // 2. Création Inscription
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

            // 3. Scolarity
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

            // 4. FinancialTransaction
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

            // 5. QR Code
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

            $qrUrl = generateSecureQRData('registration', $qrData);
            $qrCodeBase64 = $this->qrCodeService->generate($qrUrl);

            // 6. Log
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

        // ✅ Policy : accès campus selon rôle
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

            // Recalcul custom_tuition
            $newFormationId = $validated['formation_id'] ?? $registration->formation_id;
            $formation = Formation::find($newFormationId);

            $registrationData = array_intersect_key($validated, array_flip(['formation_id', 'academic_year_id']));
            $registrationData['custom_tuition'] = !empty($validated['custom_tuition'])
                ? (float) $validated['custom_tuition']
                : (float) $formation->tuition_fees;

            $registration->update($registrationData);

            // Recalcul Scolarity
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

        // ✅ Policy : 
        // - Secrétaire : uniquement les inscriptions qu'ELLE a créées
        // - Admin campus : tout son campus
        // - Admin global : tout
        $this->authorize('delete', $registration);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $student        = $registration->student;
            $studentName    = $student->first_name . ' ' . $student->last_name;
            $matricule      = $student->registration_number;

            // 1. Calcul du montant total à retirer du solde
            $initialPayment = (float) $registration->initial_payment;
            $payments       = $registration->payments()->where('status', 'confirmed')->get();
            $paymentsSum    = (float) $payments->sum('amount');
            $totalToRefund  = $initialPayment + $paymentsSum;

            // 2. Log AVANT suppression
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

            // 3. Contre-écriture : versement initial
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

            // 4. Contre-écriture : chaque paiement
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
            }

            // 5. Suppression définitive (ordre FK)
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
    // ═══ PDF ═══
    // ═══════════════════════════════════════════════════════════
    public function generateForm(Request $request, int $id)
    {
        $registration = Registration::with(['student', 'formation', 'campus', 'academicYear', 'scolarity'])->findOrFail($id);

        // ✅ Policy
        $this->authorize('view', $registration);

        $actualTuition = $registration->scolarity ? $registration->scolarity->tuition_fees : $registration->formation->tuition_fees;
        $actualPaid = $registration->scolarity ? $registration->scolarity->amount_paid : 0;
        $actualStatus = $registration->scolarity ? $registration->scolarity->status : 'unpaid';

        $qrData = [
            'matricule'         => $registration->student->registration_number,
            'name'              => $registration->student->first_name . ' ' . $registration->student->last_name,
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
            $qrCodeRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->errorCorrection('H')->generate($secureQRData);
            $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeRaw);
        } catch (\Exception $e) {
            $qrCodeBase64 = null;
            Log::error('QR Code generation failed: ' . $e->getMessage());
        }

        $pdfContent = $this->pdfService->generateRegistrationForm($registration, $qrCodeBase64);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="fiche-inscription-' . $registration->student->registration_number . '.pdf"');
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ STATS ═══
    // ═══════════════════════════════════════════════════════════
    public function stats(Request $request, int $campusId)
    {
        $user = $request->user();

        // ✅ Vérification : admin_campus / secrétaire limités à leur campus
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            if ($campusId !== (int) $user->campus_id) {
                return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
            }
        }

        // Scolarité
        $scolarityIncome  = FinancialTransaction::where('campus_id', $campusId)->where('type', 'income')->sum('amount');
        $scolarityExpense = FinancialTransaction::where('campus_id', $campusId)->where('type', 'expense')->sum('amount');
        $scolarityBalance = $scolarityIncome - $scolarityExpense;

        // Caisse
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