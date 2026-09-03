<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Registration;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    // ═══ LISTE DES ÉTUDIANTS ═══
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Student::with([
            'registrations.formation',
            'registrations.campus',
            'registrations.academicYear',
            'registrations.scolarity'
        ]);

        // Restriction campus pour admin_campus et secretary
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        }

        // Filtre campus (super_admin et admin_global uniquement)
        if ($request->has('campus_id') && in_array($user->role, ['super_admin', 'admin_global'])) {
            $query->where('campus_id', $request->campus_id);
        }

        // Filtre année scolaire
        if ($request->has('academic_year_id')) {
            $query->whereHas('registrations', function ($q) use ($request) {
                $q->where('academic_year_id', $request->academic_year_id);
            });
        }

        // Filtre formation
        if ($request->has('formation_id')) {
            $query->whereHas('registrations', function ($q) use ($request) {
                $q->where('formation_id', $request->formation_id);
            });
        }

        // Recherche par nom ou matricule
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(15);

        // Ajouter les données financières calculées
        $students->getCollection()->transform(function ($student) {
            $latestRegistration = $student->registrations->first();
            return [
                'id' => $student->id,
                'registration_number' => $student->registration_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'campus_id' => $student->campus_id,
                'latest_registration' => $latestRegistration ? [
                    'id' => $latestRegistration->id,
                    'formation' => $latestRegistration->formation,
                    'campus' => $latestRegistration->campus,
                    'academic_year' => $latestRegistration->academicYear,
                    'amount_paid' => $latestRegistration->amount_paid,
                    'balance' => $latestRegistration->balance,
                    'payment_status' => $latestRegistration->payment_status,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $students
        ]);
    }

    // ═══ DÉTAILS COMPLETS D'UN ÉTUDIANT ═══
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $student = Student::with([
            'registrations.formation',
            'registrations.campus',
            'registrations.academicYear',
            'registrations.payments',
            'registrations.scolarity'
        ])->findOrFail($id);

        // Vérification des droits campus
        if (in_array($user->role, ['admin_campus', 'secretary']) && $student->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
        }

        // Calculer les totaux financiers
        $totalPaid = 0;
        $totalBalance = 0;
        $registrationsData = $student->registrations->map(function ($reg) use (&$totalPaid, &$totalBalance) {
            $totalPaid += $reg->amount_paid;
            $totalBalance += $reg->balance;
            return [
                'id' => $reg->id,
                'formation' => $reg->formation,
                'campus' => $reg->campus,
                'academic_year' => $reg->academicYear,
                'initial_payment' => $reg->initial_payment,
                'amount_paid' => $reg->amount_paid,
                'balance' => $reg->balance,
                'payment_status' => $reg->payment_status,
                'created_at' => $reg->created_at,
                'payments' => $reg->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'payment_date' => $payment->payment_date,
                        'reference' => $payment->reference,
                        'created_at' => $payment->created_at,
                    ];
                }),
            ];
        });

        return response()->json([
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'registration_number' => $student->registration_number,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'address' => $student->address,
                    'date_of_birth' => $student->date_of_birth,
                    'parent_name' => $student->parent_name,
                    'parent_phone' => $student->parent_phone,
                    'photo' => $student->photo,
                    'campus_id' => $student->campus_id,
                    'created_at' => $student->created_at,
                ],
                'financial_summary' => [
                    'total_paid' => $totalPaid,
                    'total_balance' => $totalBalance,
                ],
                'registrations' => $registrationsData,
            ]
        ]);
    }

    // ═══ MODIFIER LES INFOS PERSONNELLES ═══
    public function update(Request $request, int $id)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus', 'secretary'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $student = Student::findOrFail($id);

        // Vérification des droits campus
        if (in_array($user->role, ['admin_campus', 'secretary']) && $student->campus_id !== $user->campus_id) {
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
        ]);

        DB::beginTransaction();
        try {
            $oldData = $student->only(['first_name', 'last_name', 'email', 'phone', 'address', 'date_of_birth', 'parent_name', 'parent_phone']);
            
            $student->update($validated);

            $this->activityLogService->log(
                action: 'updated',
                targetType: 'student',
                targetId: $student->id,
                targetName: $student->first_name . ' ' . $student->last_name,
                oldData: $oldData,
                newData: $validated,
                changes: 'Modification des informations personnelles de ' . $student->first_name . ' ' . $student->last_name,
                campusId: $student->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('StudentController@update', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la modification'], 500);
        }

        return response()->json([
            'message' => 'Informations modifiées avec succès',
            'data' => $student
        ]);
    }
}