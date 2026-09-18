<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Registration;
use App\Models\Campus;
use App\Services\ActivityLogService;
use App\Services\PDFStorageService;  
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private PDFStorageService $pdfStorage
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
                    'residence' => $student->residence,
                    'date_of_birth' => $student->date_of_birth,
                    'highest_diploma' => $student->highest_diploma,
                    'diploma_year' => $student->diploma_year,
                    'languages' => $student->languages,
                    'parent_name' => $student->parent_name,
                    'parent_phone' => $student->parent_phone,
                    'place_of_birth' => $student->place_of_birth,
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
    // ═══ MODIFIER LES INFOS PERSONNELLES ═══
public function update(Request $request, int $id)
{
    $user = $request->user();
    $student = Student::findOrFail($id);

    if (in_array($user->role, ['admin_campus', 'secretary']) && $student->campus_id !== $user->campus_id) {
        return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
    }

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
        'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    DB::beginTransaction();
    try {
        $oldData = $student->only([
            'first_name', 'last_name', 'email', 'phone', 'residence', 'date_of_birth',
            'place_of_birth', 'highest_diploma', 'diploma_year', 'languages',
            'parent_name', 'parent_phone', 'photo'
        ]);

        // ✅ CORRECTION DATE DE NAISSANCE : chaîne vide → null
        if (isset($validated['date_of_birth']) && $validated['date_of_birth'] === '') {
            $validated['date_of_birth'] = null;
        }

        // ✅ CORRECTION LANGUES : Encoder en JSON
        if (isset($validated['languages']) && is_array($validated['languages'])) {
            $validated['languages'] = json_encode($validated['languages']);
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');

            if ($student->photo && Storage::disk('private')->exists($student->photo)) {
                Storage::disk('private')->delete($student->photo);
            }

            $path = $file->store('students', 'private');
            $validated['photo'] = $path;
        }

        $student->update($validated);

        // ═══════════════════════════════════════════════════════════
        // ✅ NOUVEAU : Invalider tous les PDF liés à cet étudiant
        // ═══════════════════════════════════════════════════════════
        $this->invalidateStudentPDFs($student);

        $this->activityLogService->log(
            action: 'updated',
            targetType: 'student',
            targetId: $student->id,
            targetName: $student->first_name . ' ' . $student->last_name,
            oldData: $oldData,
            newData: $validated,
            changes: 'Modification des informations de ' . $student->first_name . ' ' . $student->last_name,
            campusId: $student->campus_id
        );

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        Log::error('StudentController@update', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Erreur lors de la modification : ' . $e->getMessage()], 500);
    }

    return response()->json([
        'message' => 'Informations modifiées avec succès',
        'data' => $student->fresh()
    ]);
}

/**
 * ✅ Invalide tous les PDF d'un étudiant :
 * - Sa ou ses fiches d'inscription
 * - Tous ses reçus de paiement
 */
private function invalidateStudentPDFs(Student $student): void
{
    // Recharge avec les relations nécessaires
    $student->load(['registrations.payments', 'registrations.scolarity']);

    foreach ($student->registrations as $registration) {
        // ✅ Supprimer la fiche d'inscription du disque
        $this->pdfStorage->deleteRegistrationPDF($registration);

        // ✅ Mettre à null le chemin en BD
        $registration->update([
            'pdf_path' => null,
            'pdf_generated_at' => null,
        ]);

        // ✅ Supprimer tous les reçus liés
        foreach ($registration->payments as $payment) {
            $this->pdfStorage->deleteReceiptPDF($payment);

            $payment->update([
                'receipt_path' => null,
                'receipt_generated_at' => null,
            ]);
        }
    }
}

    // ═══ SERVIR LA PHOTO ═══
    public function getPhoto(Request $request, int $id)
    {
        $user = $request->user();
        $student = Student::findOrFail($id);

        if (in_array($user->role, ['admin_campus', 'secretary']) && $student->campus_id !== $user->campus_id) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        if (!$student->photo || !Storage::disk('private')->exists($student->photo)) {
            return response('', 204);
        }

        $path = Storage::disk('private')->path($student->photo);
        $file = Storage::disk('private')->get($student->photo);
        $mimeType = mime_content_type($path);

        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    // ═══ RAPPORT DÉTAILLÉ ÉTAT DE SCOLARITÉ ═══
    public function scholarshipReport(Request $request)
    {
        $user = $request->user();

        $campusScope = function ($query) use ($user) {
            if (in_array($user->role, ['admin_campus', 'secretary'])) {
                $query->where('campus_id', $user->campus_id);
            }
        };

        // ✅ AJOUT 'scolarity' pour le prix réel
        $query = Registration::with(['student', 'formation', 'campus', 'academicYear', 'scolarity'])
            ->where($campusScope);

        if ($request->filled('campus_id') && in_array($user->role, ['super_admin', 'admin_global'])) {
            $query->where('campus_id', $request->campus_id);
        }
        if ($request->filled('formation_id')) {
            $query->where('formation_id', $request->formation_id);
        }
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }
        if ($request->filled('payment_status') && in_array($request->payment_status, ['unpaid', 'partial', 'paid'])) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderBy('campus_id')
            ->orderBy('formation_id')
            ->orderBy('student_id')
            ->get();

        $groupedData = [];
        $grandTotalExpected = 0;
        $grandTotalPaid = 0;
        $grandTotalBalance = 0;
        $grandTotalStudents = 0;

        if (in_array($user->role, ['super_admin', 'admin_global']) && !$request->filled('campus_id')) {
            $byCampus = $registrations->groupBy('campus_id');
            foreach ($byCampus as $campusId => $campusRegs) {
                $campus = $campusRegs->first()->campus;
                $campusData = [
                    'campus' => $campus,
                    'campus_total_expected' => 0,
                    'campus_total_paid' => 0,
                    'campus_total_balance' => 0,
                    'campus_total_students' => 0,
                    'formations' => [],
                ];

                $byFormation = $campusRegs->groupBy('formation_id');
                foreach ($byFormation as $formationId => $formRegs) {
                    $formation = $formRegs->first()->formation;

                    // ✅ Prix réel : scolarity.tuition_fees > custom_tuition > formation.tuition_fees
                    $formExpected = $formRegs->sum(function ($reg) {
                        return (float) (
                            $reg->scolarity?->tuition_fees
                            ?? $reg->custom_tuition
                            ?? $reg->formation?->tuition_fees
                            ?? 0
                        );
                    });
                    $formPaid = $formRegs->sum('amount_paid');
                    $formBalance = $formRegs->sum('balance');

                    $campusData['campus_total_expected'] += $formExpected;
                    $campusData['campus_total_paid'] += $formPaid;
                    $campusData['campus_total_balance'] += $formBalance;
                    $campusData['campus_total_students'] += $formRegs->count();

                    $campusData['formations'][] = [
                        'formation' => $formation,
                        'total_expected' => $formExpected,
                        'total_paid' => $formPaid,
                        'total_balance' => $formBalance,
                        'student_count' => $formRegs->count(),
                        'students' => $formRegs->map(function ($r) {
                            // ✅ Prix réel par étudiant
                            $actualTuition = (float) (
                                $r->scolarity?->tuition_fees
                                ?? $r->custom_tuition
                                ?? $r->formation?->tuition_fees
                                ?? 0
                            );

                            return [
                                'id' => $r->id,
                                'matricule' => $r->student->registration_number,
                                'name' => $r->student->first_name . ' ' . $r->student->last_name,
                                'phone' => $r->student->phone,
                                'total_expected' => $actualTuition,
                                'amount_paid' => $r->amount_paid,
                                'balance' => $r->balance,
                                'status' => $r->payment_status,
                            ];
                        })->values(),
                    ];
                }
                $groupedData[] = $campusData;
                $grandTotalExpected += $campusData['campus_total_expected'];
                $grandTotalPaid += $campusData['campus_total_paid'];
                $grandTotalBalance += $campusData['campus_total_balance'];
                $grandTotalStudents += $campusData['campus_total_students'];
            }
        } else {
            $campus = $registrations->first()?->campus;
            $campusData = [
                'campus' => $campus,
                'campus_total_expected' => 0,
                'campus_total_paid' => 0,
                'campus_total_balance' => 0,
                'campus_total_students' => 0,
                'formations' => [],
            ];

            $byFormation = $registrations->groupBy('formation_id');
            foreach ($byFormation as $formationId => $formRegs) {
                $formation = $formRegs->first()->formation;

                // ✅ Prix réel
                $formExpected = $formRegs->sum(function ($reg) {
                    return (float) (
                        $reg->scolarity?->tuition_fees
                        ?? $reg->custom_tuition
                        ?? $reg->formation?->tuition_fees
                        ?? 0
                    );
                });
                $formPaid = $formRegs->sum('amount_paid');
                $formBalance = $formRegs->sum('balance');

                $campusData['campus_total_expected'] += $formExpected;
                $campusData['campus_total_paid'] += $formPaid;
                $campusData['campus_total_balance'] += $formBalance;
                $campusData['campus_total_students'] += $formRegs->count();

                $campusData['formations'][] = [
                    'formation' => $formation,
                    'total_expected' => $formExpected,
                    'total_paid' => $formPaid,
                    'total_balance' => $formBalance,
                    'student_count' => $formRegs->count(),
                    'students' => $formRegs->map(function ($r) {
                        // ✅ Prix réel par étudiant
                        $actualTuition = (float) (
                            $r->scolarity?->tuition_fees
                            ?? $r->custom_tuition
                            ?? $r->formation?->tuition_fees
                            ?? 0
                        );

                        return [
                            'id' => $r->id,
                            'matricule' => $r->student->registration_number,
                            'name' => $r->student->first_name . ' ' . $r->student->last_name,
                            'phone' => $r->student->phone,
                            'total_expected' => $actualTuition,
                            'amount_paid' => $r->amount_paid,
                            'balance' => $r->balance,
                            'status' => $r->payment_status,
                        ];
                    })->values(),
                ];
            }
            $groupedData[] = $campusData;
            $grandTotalExpected = $campusData['campus_total_expected'];
            $grandTotalPaid = $campusData['campus_total_paid'];
            $grandTotalBalance = $campusData['campus_total_balance'];
            $grandTotalStudents = $campusData['campus_total_students'];
        }

        return response()->json([
            'data' => [
                'grouped_data' => $groupedData,
                'grand_total' => [
                    'students' => $grandTotalStudents,
                    'expected' => $grandTotalExpected,
                    'paid' => $grandTotalPaid,
                    'balance' => $grandTotalBalance,
                ],
                'filter_info' => $this->buildScholarshipFilterInfo($request, $user),
            ]
        ]);
    }

    // ═══ LISTE SIMPLE DES ÉTUDIANTS ═══
    public function simpleList(Request $request)
    {
        $user = $request->user();

        $query = Student::with(['registrations.formation', 'registrations.campus'])
            ->whereHas('registrations', function ($q) use ($user) {
                if (in_array($user->role, ['admin_campus', 'secretary'])) {
                    $q->where('campus_id', $user->campus_id);
                }
            });

        if ($request->filled('campus_id') && in_array($user->role, ['super_admin', 'admin_global'])) {
            $query->whereHas('registrations', function ($q) use ($request) {
                $q->where('campus_id', $request->campus_id);
            });
        }
        if ($request->filled('formation_id')) {
            $query->whereHas('registrations', function ($q) use ($request) {
                $q->where('formation_id', $request->formation_id);
            });
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('last_name')->orderBy('first_name')->get()->map(function ($student) {
            $latestReg = $student->registrations->first();
            return [
                'matricule' => $student->registration_number,
                'name' => $student->first_name . ' ' . $student->last_name,
                'phone' => $student->phone,
                'email' => $student->email,
                'formation' => $latestReg?->formation?->name ?? '-',
                'campus' => $latestReg?->campus?->name ?? '-',
            ];
        });

        return response()->json([
            'data' => [
                'students' => $students,
                'total' => $students->count(),
            ]
        ]);
    }

    // ═══ GÉNÉRATION PDF RAPPORT SCOLARITÉ ═══
    public function generateScholarshipReport(Request $request)
    {
        $user = $request->user();
        $reportData = $this->scholarshipReport($request)->getData(true);
        $data = $reportData['data'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.scholarship_report', [
            'groupedData' => $data['grouped_data'],
            'grandTotal' => $data['grand_total'],
            'filterInfo' => $data['filter_info'],
            'generatedAt' => now(),
            'generatedBy' => $user->first_name . ' ' . $user->last_name,
            'userRole' => $user->role,
        ]);

        return $pdf->download('rapport-scolarite-' . now()->format('Y-m-d-His') . '.pdf');
    }

    // ═══ GÉNÉRATION PDF LISTE SIMPLE ═══
    public function generateSimpleList(Request $request)
    {
        $user = $request->user();
        $listData = $this->simpleList($request)->getData(true);
        $data = $listData['data'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.student_list', [
            'students' => $data['students'],
            'total' => $data['total'],
            'generatedAt' => now(),
            'generatedBy' => $user->first_name . ' ' . $user->last_name,
        ]);

        return $pdf->download('liste-etudiants-' . now()->format('Y-m-d-His') . '.pdf');
    }

    // ═══ HELPERS ═══
    private function buildScholarshipFilterInfo(Request $request, $user): string
    {
        $parts = [];
        if ($user->role === 'admin_campus' || $user->role === 'secretary') {
            $campus = Campus::find($user->campus_id);
            $parts[] = 'Campus : ' . ($campus->name ?? 'N/A');
        } elseif ($request->filled('campus_id')) {
            $campus = Campus::find($request->campus_id);
            $parts[] = 'Campus : ' . ($campus->name ?? 'N/A');
        } else {
            $parts[] = 'Tous les campus';
        }
        if ($request->filled('formation_id')) {
            $formation = \App\Models\Formation::find($request->formation_id);
            $parts[] = 'Formation : ' . ($formation->name ?? 'N/A');
        }
        if ($request->filled('payment_status')) {
            $labels = ['unpaid' => 'Non payé', 'partial' => 'Partiel', 'paid' => 'Soldé'];
            $parts[] = 'Statut : ' . ($labels[$request->payment_status] ?? $request->payment_status);
        }
        return implode(' | ', $parts);
    }
}