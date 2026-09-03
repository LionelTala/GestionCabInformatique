<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Scolarity;
use App\Models\Registration;
use App\Models\Payment;
use App\Models\FinancialTransaction;
use App\Models\Formation;
use App\Models\ActivityLog;
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

    // ═══ LISTE ═══
    public function index(Request $request)
    {
        $query = Registration::with([
            'student:id,first_name,last_name,registration_number,email,phone',
            'formation:id,name,abbreviation,tuition_fees',
            'campus:id,name,city',
            'academicYear:id,label',
            'payments', // Indispensable pour que le front calcule ou affiche les versements
        ]);

        $user = $request->user();
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

    // ═══ DÉTAILS ═══
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $registration = Registration::with(['student', 'formation', 'campus', 'academicYear', 'payments'])->findOrFail($id);

        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        return response()->json(['data' => $registration]);
    }

    // ═══ CRÉER ═══
   public function store(Request $request)
{
    $user = $request->user();
    if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary'])) {
        return response()->json(['message' => 'Accès non autorisé'], 403);
    }

    $validated = $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string',
        'date_of_birth' => 'nullable|date',
        'parent_name' => 'nullable|string|max:255',
        'parent_phone' => 'nullable|string|max:20',
        'formation_id' => 'required|exists:formations,id',
        'campus_id' => 'required|exists:campuses,id',
        'academic_year_id' => 'required|exists:academic_years,id',
        'initial_payment' => 'nullable|numeric|min:0', // Premier versement optionnel
        'photo' => 'nullable|image|max:2048',
    ]);

    $campusId = in_array($user->role, ['admin_campus', 'secretary']) ? $user->campus_id : $validated['campus_id'];
    $formation = Formation::findOrFail($validated['formation_id']);
    $initialPayment = $validated['initial_payment'] ?? 0;

    // Génération matricule
    $year = now()->format('y');
    $abbreviation = $formation->abbreviation;
    do {
        $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $registrationNumber = $year . $abbreviation . $random;
    } while (Student::where('registration_number', $registrationNumber)->exists());

    DB::beginTransaction();
    try {
        // 1. Création Étudiant
        $student = Student::create([
            'campus_id' => $campusId,
            'formation_id' => $validated['formation_id'],
            'academic_year_id' => $validated['academic_year_id'],
            'registration_number' => $registrationNumber,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'parent_name' => $validated['parent_name'] ?? null,
            'parent_phone' => $validated['parent_phone'] ?? null,
            'created_by' => $user->id,
        ]);

        if ($request->hasFile('photo')) {
            $student->photo = $request->file('photo')->storeAs('students', $registrationNumber . '.jpg', 'private');
            $student->save();
        }

        // 2. Création Inscription (initial_payment figé à jamais)
        $registration = Registration::create([
            'student_id' => $student->id,
            'campus_id' => $campusId,
            'formation_id' => $validated['formation_id'],
            'academic_year_id' => $validated['academic_year_id'],
            'initial_payment' => $initialPayment,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        // 3. Création de la Scolarity (source de vérité financière)
        $tuitionFees = $formation->tuition_fees;
        $amountPaid = $initialPayment; // Au départ, seul l'initial_payment compte
        $balance = $tuitionFees - $amountPaid;
        $status = $amountPaid <= 0 ? 'unpaid' : ($amountPaid >= $tuitionFees ? 'paid' : 'partial');

        $scolarity = Scolarity::create([
            'registration_id' => $registration->id,
            'student_id' => $student->id,
            'campus_id' => $campusId,
            'tuition_fees' => $tuitionFees,
            'amount_paid' => $amountPaid,
            'balance' => $balance,
            'status' => $status,
        ]);

        // 4. FinancialTransaction si initial_payment > 0
        if ($initialPayment > 0) {
            FinancialTransaction::create([
                'registration_id' => $registration->id,
                'campus_id' => $campusId,
                'student_id' => $student->id,
                'type' => 'income',
                'category' => 'registration', // Premier versement = catégorie registration
                'amount' => $initialPayment,
                'description' => 'Versement initial - ' . $student->first_name . ' ' . $student->last_name,
                'reference' => 'INIT-' . now()->format('Ymd') . '-' . str_pad($registration->id, 4, '0', STR_PAD_LEFT),
                'created_by' => $user->id,
            ]);
        }

                // ... (après la création de la Scolarity et de la FinancialTransaction) ...

                // 1. Préparer les données pour le QR Code
        $qrData = [
            'matricule'         => $student->registration_number,
            'name'              => $student->first_name . ' ' . $student->last_name,
            'formation'         => $formation->name,
            'formation_code'    => $formation->abbreviation,
            'campus'            => $registration->campus?->name ?? 'CAB Informatique',
            'registration_id'   => $registration->id,
            'registration_date' => $registration->created_at->format('d/m/Y'),
            'academic_year'     => $registration->academicYear->label ?? (date('Y') . '-' . (date('Y') + 1)),
            'tuition_fees'      => $formation->tuition_fees,
            'amount_paid'       => $registration->amount_paid,
            'financial_status'  => $registration->payment_status,
        ];

        // 2. Générer la signature DIRECTEMENT (pas besoin de parser l'URL)
        $signature = generateDocumentSignature($qrData);
        
        // 3. Stocker la signature en base
        $registration->qr_code_hash = $signature;
        $registration->save();

        // 4. Générer l'URL complète pour le QR Code visuel
        $qrUrl = generateSecureQRData('registration', $qrData);
        
        // 5. Générer le QR Code (le service retourne déjà du base64)
        $qrCodeBase64 = $this->qrCodeService->generate($qrUrl);

        // 7. LOG UNIQUE
        $logChanges = 'Nouvelle inscription : ' . $student->first_name . ' ' . $student->last_name . ' — ' . $formation->name;
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
        return response()->json(['message' => "Erreur lors de la création de l'inscription"], 500);
    }

     return response()->json([
        'message' => 'Inscription réussie',
        'data' => [
            'student' => $student,
            'registration' => $registration
        ],
    ], 201);
}
    // ═══ MODIFIER ═══
    public function update(Request $request, int $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $registration = Registration::with(['student', 'payments'])->findOrFail($id);
        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'parent_name' => 'nullable|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'formation_id' => 'sometimes|exists:formations,id',
            'academic_year_id' => 'sometimes|exists:academic_years,id',
        ]);

        DB::beginTransaction();
        try {
            $oldData = $registration->only(['formation_id', 'academic_year_id']);
            $oldData = array_merge($oldData, $registration->student->only(['first_name', 'last_name', 'phone', 'email']));

            $student = $registration->student;
            $student->update(array_intersect_key($validated, array_flip(['first_name', 'last_name', 'email', 'phone', 'address', 'date_of_birth', 'parent_name', 'parent_phone'])));
            
            $registration->update(array_intersect_key($validated, array_flip(['formation_id', 'academic_year_id'])));

            $this->activityLogService->log(
                action: 'updated',
                targetType: 'registration',
                targetId: $registration->id,
                targetName: $student->first_name . ' ' . $student->last_name,
                oldData: $oldData,
                newData: $registration->fresh()->only(['formation_id', 'academic_year_id']) + $student->fresh()->only(['first_name', 'last_name', 'phone', 'email']),
                changes: 'Modification des informations de l\'étudiant ou de la formation',
                campusId: $registration->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('update() - Erreur', ['message' => $e->getMessage()]);
            return response()->json(['message' => "Erreur lors de la modification"], 500);
        }

        return response()->json(['message' => 'Modifié avec succès', 'data' => $registration->load(['student', 'formation', 'campus', 'academicYear', 'payments'])]);
    }

    // ═══ SUPPRIMER ═══
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $registration = Registration::with(['student', 'payments'])->findOrFail($id);
        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        DB::beginTransaction();
        try {
            $studentName = $registration->student->first_name . ' ' . $registration->student->last_name;
            
            // Log avant suppression (pour avoir les données)
            $this->activityLogService->log(
                action: 'deleted',
                targetType: 'registration',
                targetId: $registration->id,
                targetName: $studentName,
                oldData: $registration->toArray(),
                changes: 'Suppression de l\'inscription de ' . $studentName,
                campusId: $registration->campus_id
            );

            $registration->student()->delete(); // Soft delete
            $registration->delete(); // Soft delete

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('destroy() - Erreur', ['message' => $e->getMessage()]);
            return response()->json(['message' => "Erreur lors de la suppression"], 500);
        }

        return response()->json(['message' => 'Inscription supprimée avec succès']);
    }

    // ═══ RESTAURER ═══
    // public function restore(Request $request, int $id)
    // {
    //     $user = $request->user();
    //     if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
    //         return response()->json(['message' => 'Accès non autorisé'], 403);
    //     }

    //     $registration = Registration::withTrashed()->findOrFail($id);
    //     if (in_array($user->role, ['admin_campus']) && $registration->campus_id !== $user->campus_id) {
    //         return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         if ($registration->student()->withTrashed()->exists()) {
    //             $registration->student()->withTrashed()->restore();
    //         }
    //         $registration->restore();

    //         $this->activityLogService->log(
    //             action: 'restored',
    //             targetType: 'registration',
    //             targetId: $registration->id,
    //             targetName: $registration->student->first_name . ' ' . $registration->student->last_name,
    //             newData: ['status' => 'restored'],
    //             changes: 'Restauration de l\'inscription',
    //             campusId: $registration->campus_id
    //         );

    //         DB::commit();
    //     } catch (Throwable $e) {
    //         DB::rollBack();
    //         Log::error('restore() - Erreur', ['message' => $e->getMessage()]);
    //         return response()->json(['message' => "Erreur lors de la restauration"], 500);
    //     }

    //     return response()->json(['message' => 'Inscription restaurée avec succès', 'data' => $registration->load(['student', 'formation', 'campus', 'academicYear', 'payments'])]);
    // }

    // ═══ PDF ═══
       // ═══ PDF ═══
        public function generateForm(Request $request, int $id)
    {
        $user = $request->user();
        $registration = Registration::with(['student', 'formation', 'campus', 'academicYear'])->findOrFail($id);

        if (in_array($user->role, ['admin_campus', 'secretary']) && $registration->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        // 1. Préparer les données
        $qrData = [
            'matricule'         => $registration->student->registration_number,
            'name'              => $registration->student->first_name . ' ' . $registration->student->last_name,
            'formation'         => $registration->formation->name,
            'formation_code'    => $registration->formation->abbreviation,
            'campus'            => $registration->campus?->name ?? 'CAB Informatique',
            'registration_id'   => $registration->id,
            'registration_date' => $registration->created_at->format('d/m/Y'),
            'academic_year'     => $registration->academicYear->label ?? (date('Y') . '-' . (date('Y') + 1)),
            'tuition_fees'      => $registration->formation->tuition_fees,
            'amount_paid'       => $registration->amount_paid,
            'financial_status'  => $registration->payment_status,
        ];

        // 2. Générer l'URL sécurisée
        $secureQRData = generateSecureQRData('registration', $qrData);

        // 3. Générer le QR Code EXACTEMENT comme dans ton ancien projet
        try {
            $qrCodeRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->errorCorrection('H')->generate($secureQRData);
            $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeRaw);
        } catch (\Exception $e) {
            $qrCodeBase64 = null;
            Log::error('QR Code generation failed: ' . $e->getMessage());
        }

        // 4. Générer le PDF
        $pdfContent = $this->pdfService->generateRegistrationForm($registration, $qrCodeBase64);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="fiche-inscription-' . $registration->student->registration_number . '.pdf"');
    }

    // ═══ STATS ═══
    public function stats(Request $request, int $campusId)
    {
        $user = $request->user();
        if (in_array($user->role, ['admin_campus', 'secretary']) && $campusId !== (int) $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        // Note: Ici, on somme les transactions, pas le total_amount des registrations, 
        // car total_amount est un cumul qui peut être faussé si on ne filtre pas par année.
        $income = \App\Models\FinancialTransaction::where('campus_id', $campusId)->where('type', 'income')->sum('amount');
        $expense = \App\Models\FinancialTransaction::where('campus_id', $campusId)->where('type', 'expense')->sum('amount');

        return response()->json([
            'data' => [
                'total_income' => $income,
                'total_expense' => $expense,
                'balance' => $income - $expense,
                'count' => Registration::where('campus_id', $campusId)->count(),
            ],
        ]);
    }
}