<?php
// app/Http/Controllers/Api/AttestationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attestation;
use App\Models\Registration;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AttestationController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    // ═══════════════════════════════════════════════════════════
    // ═══ LISTE ═══
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', Attestation::class);

        $user = $request->user();
        $status = $request->get('status', 'pending');

        $query = Attestation::with([
            'student:id,first_name,last_name,registration_number,email,phone',
            'registration:id,student_id,formation_id,campus_id',
            'registration.formation:id,name,abbreviation',
            'campus:id,name,city',
            'requestedBy:id,first_name,last_name',
            'settledBy:id,first_name,last_name',
        ])->orderBy('created_at', 'desc');

        // Scope par rôle
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        } elseif (in_array($user->role, ['super_admin', 'admin_global']) && $request->filled('campus_id')) {
            $query->where('campus_id', $request->integer('campus_id'));
        }

        // Filtre par statut
        if (in_array($status, ['pending', 'ready'])) {
            $query->where('status', $status);
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        // Stats
        $statsQuery = Attestation::query();
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $statsQuery->where('campus_id', $user->campus_id);
        } elseif (in_array($user->role, ['super_admin', 'admin_global']) && $request->filled('campus_id')) {
            $statsQuery->where('campus_id', $request->integer('campus_id'));
        }

        $pendingCount = (clone $statsQuery)->where('status', 'pending')->count();
        $readyCount   = (clone $statsQuery)->where('status', 'ready')->count();

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 15)),
            'meta' => [
                'status'        => $status,
                'pending_count' => $pendingCount,
                'ready_count'   => $readyCount,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ RECHERCHE ÉTUDIANT ═══
    // ═══════════════════════════════════════════════════════════
    public function searchStudents(Request $request)
    {
        $this->authorize('viewAny', Attestation::class);

        $query = $request->get('q', '');
        $user = $request->user();

        $registrations = Registration::with([
            'student:id,first_name,last_name,registration_number,email,phone',
            'formation:id,name,abbreviation',
            'campus:id,name',
            'scolarity:id,registration_id,tuition_fees,amount_paid,balance,status',
        ])
            ->whereDoesntHave('student', fn($q) => $q->whereNotNull('deleted_at'))
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
                    'name'         => $reg->student->first_name . ' ' . $reg->student->last_name,
                    'email'        => $reg->student->email,
                    'phone'        => $reg->student->phone,
                    'formation'    => $reg->formation->name,
                    'campus'       => $reg->campus->name,
                    'campus_id'    => $reg->campus_id,
                    'tuition_fees' => (float) ($reg->scolarity?->tuition_fees ?? 0),
                    'amount_paid'  => (float) ($reg->scolarity?->amount_paid ?? 0),
                    'balance'      => (float) ($reg->scolarity?->balance ?? 0),
                    'status'       => $reg->scolarity?->status ?? 'unpaid',
                ];
            }),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ CRÉER ═══
    // ═══════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $this->authorize('create', Attestation::class);

        $user = $request->user();

        $validated = $request->validate([
            'registration_id' => 'required|exists:registrations,id',
        ]);

        $registration = Registration::with(['student', 'campus', 'formation'])->findOrFail($validated['registration_id']);

        // Vérification campus
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            if ((int) $registration->campus_id !== (int) $user->campus_id) {
                return response()->json(['message' => 'Accès non autorisé à ce campus'], 403);
            }
        }

        // Vérifier qu'il n'y a pas déjà une attestation pour cet étudiant
        $existing = Attestation::where('registration_id', $registration->id)->exists();

        if ($existing) {
            return response()->json([
                'message' => 'Une attestation existe déjà pour cet étudiant.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Générer référence : ATT-YYYY-XXXX
            $year = now()->format('Y');
            $lastId = Attestation::whereYear('created_at', now()->year)->max('id') ?? 0;
            $reference = 'ATT-' . $year . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            $attestation = Attestation::create([
                'reference'       => $reference,
                'registration_id' => $registration->id,
                'student_id'      => $registration->student_id,
                'campus_id'       => $registration->campus_id,
                'status'          => 'pending',
                'requested_by'    => $user->id,
                'requested_at'    => now(),
            ]);

            $studentName = $registration->student->first_name . ' ' . $registration->student->last_name;

            $this->activityLogService->log(
                action: 'created',
                targetType: 'attestation',
                targetId: $attestation->id,
                targetName: $reference,
                newData: $attestation->toArray(),
                changes: "Nouvelle demande d'attestation de fin de formation pour {$studentName}",
                campusId: $registration->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('AttestationController@store', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }

        return response()->json([
            'message' => 'Demande d\'attestation créée avec succès',
            'data'    => $attestation->load(['student', 'campus', 'registration.formation']),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ RÉGLER (pending → ready) ═══
    // ═══════════════════════════════════════════════════════════
    public function settle(Request $request, int $id)
    {
        $attestation = Attestation::with(['student'])->findOrFail($id);
        $this->authorize('settle', $attestation);

        if (!$attestation->isPending()) {
            return response()->json(['message' => 'Cette attestation est déjà réglée.'], 422);
        }

        $user = $request->user();

        DB::beginTransaction();
        try {
            $attestation->update([
                'status'     => 'ready',
                'settled_by' => $user->id,
                'settled_at' => now(),
            ]);

            $studentName = $attestation->student->first_name . ' ' . $attestation->student->last_name;

            $this->activityLogService->log(
                action: 'updated',
                targetType: 'attestation',
                targetId: $attestation->id,
                targetName: $attestation->reference,
                oldData: ['status' => 'pending'],
                newData: ['status' => 'ready'],
                changes: "Attestation {$attestation->reference} réglée pour {$studentName}",
                campusId: $attestation->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('AttestationController@settle', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du règlement'], 500);
        }

        return response()->json([
            'message' => 'Attestation réglée avec succès',
            'data'    => $attestation->fresh()->load(['student', 'campus', 'settledBy']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ REMETTRE EN ATTENTE (ready → pending) ═══
    // ═══════════════════════════════════════════════════════════
    public function unsettle(Request $request, int $id)
    {
        $attestation = Attestation::with(['student'])->findOrFail($id);
        $this->authorize('settle', $attestation);

        if (!$attestation->isReady()) {
            return response()->json(['message' => 'Cette attestation n\'est pas réglée.'], 422);
        }

        DB::beginTransaction();
        try {
            $attestation->update([
                'status'     => 'pending',
                'settled_by' => null,
                'settled_at' => null,
            ]);

            $studentName = $attestation->student->first_name . ' ' . $attestation->student->last_name;

            $this->activityLogService->log(
                action: 'updated',
                targetType: 'attestation',
                targetId: $attestation->id,
                targetName: $attestation->reference,
                oldData: ['status' => 'ready'],
                newData: ['status' => 'pending'],
                changes: "Attestation {$attestation->reference} remise en attente pour {$studentName}",
                campusId: $attestation->campus_id
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('AttestationController@unsettle', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la remise en attente'], 500);
        }

        return response()->json([
            'message' => 'Attestation remise en attente',
            'data'    => $attestation->fresh()->load(['student', 'campus']),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ STATS ═══
    // ═══════════════════════════════════════════════════════════
    public function stats(Request $request)
    {
        $user = $request->user();
        $query = Attestation::query();

        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            $query->where('campus_id', $user->campus_id);
        } elseif (in_array($user->role, ['super_admin', 'admin_global']) && $request->filled('campus_id')) {
            $query->where('campus_id', $request->integer('campus_id'));
        }

        $pending = (clone $query)->where('status', 'pending')->count();
        $ready   = (clone $query)->where('status', 'ready')->count();

        return response()->json([
            'data' => [
                'pending' => $pending,
                'ready'   => $ready,
                'total'   => $pending + $ready,
            ],
        ]);
    }
    // app/Http/Controllers/Api/AttestationController.php

// ═══════════════════════════════════════════════════════════
// ═══ ANNULER UNE DEMANDE EN ATTENTE ═══
// ═══════════════════════════════════════════════════════════
public function cancel(Request $request, int $id)
{
    $attestation = Attestation::with(['student'])->findOrFail($id);

    $this->authorize('cancel', $attestation);

    // ✅ On ne peut annuler QUE les demandes en attente
    if (!$attestation->isPending()) {
        return response()->json([
            'message' => 'Seules les demandes en attente peuvent être annulées.'
        ], 422);
    }

    $user = $request->user();

    DB::beginTransaction();
    try {
        $studentName = $attestation->student->first_name . ' ' . $attestation->student->last_name;
        $reference = $attestation->reference;

        // Log AVANT suppression (traçabilité)
        $this->activityLogService->log(
            action: 'deleted',
            targetType: 'attestation',
            targetId: $attestation->id,
            targetName: $reference,
            oldData: $attestation->toArray(),
            changes: "Annulation de la demande d'attestation de fin de formation de {$studentName}",
            campusId: $attestation->campus_id
        );

        // Traçabilité : qui a annulé
        $attestation->update(['cancelled_by' => $user->id]);

        // Suppression définitive
        $attestation->delete();

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        Log::error('AttestationController@cancel', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Erreur lors de l\'annulation'], 500);
    }

    return response()->json(['message' => 'Demande annulée avec succès']);
}
}