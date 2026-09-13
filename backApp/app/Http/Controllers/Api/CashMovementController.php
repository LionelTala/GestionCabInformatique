<?php
// app/Http/Controllers/Api/CashMovementController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class CashMovementController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    // ═══════════════════════════════════════════════════════════
    // ═══ LISTE DES MOUVEMENTS DE CAISSE ═══
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $this->authorize('viewAny', CashMovement::class);

        $user = $request->user();

        $query = CashMovement::with(['campus:id,name', 'createdBy:id,first_name,last_name'])
            ->orderBy('created_at', 'desc');

        // ─── SCOPE PAR RÔLE ────────────────────────────────
        if ($user->role === 'secretary') {
            // ✅ Secrétaire : UNIQUEMENT ses propres mouvements
            $query->where('created_by', $user->id)
                  ->where('campus_id', $user->campus_id);
        } elseif ($user->role === 'admin_campus') {
            // ✅ Admin campus : tout son campus
            $query->where('campus_id', $user->campus_id);
        } elseif (in_array($user->role, ['super_admin', 'admin_global'])) {
            // ✅ Admin global : tout, filtrable
            if ($request->filled('campus_id')) {
                $query->where('campus_id', $request->integer('campus_id'));
            }
        }

        // ─── FILTRE TYPE ───────────────────────────────────
        if ($request->filled('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('type', $request->type);
        }

        // ─── FILTRE CATÉGORIE ──────────────────────────────
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // ─── FILTRE PÉRIODE ────────────────────────────────
        [$dateFrom, $dateTo] = $this->resolvePeriod(
            $request->get('period', 'today'),
            $request
        );
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        // ─── RECHERCHE ─────────────────────────────────────
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        // ─── STATS DE LA PÉRIODE ───────────────────────────
        $totalIncome  = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $balance      = $totalIncome - $totalExpense;

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 15)),
            'meta' => [
                'period'         => $request->get('period', 'today'),
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'total_income'   => (float) $totalIncome,
                'total_expense'  => (float) $totalExpense,
                'balance'        => (float) $balance,
                'total_count'    => (clone $query)->count(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ CRÉER UN MOUVEMENT ═══
    // ═══════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $this->authorize('create', CashMovement::class);

        $user = $request->user();

        $validated = $request->validate([
            'campus_id'   => 'required|exists:campuses,id',
            'type'        => 'required|in:income,expense',
            'category'    => 'required|string|max:50',
            'amount'      => 'required|numeric|min:1',
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'attachment'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // ✅ Vérifier que la catégorie est valide selon le type
        $config = config("cash.categories.{$validated['type']}", []);
        if (!array_key_exists($validated['category'], $config)) {
            return response()->json([
                'message' => "Catégorie invalide pour le type {$validated['type']}.",
                'valid_categories' => array_keys($config),
            ], 422);
        }

        // ✅ Restriction campus : secrétaire / admin_campus → leur campus
        if (in_array($user->role, ['admin_campus', 'secretary'])) {
            if ((int) $validated['campus_id'] !== (int) $user->campus_id) {
                return response()->json([
                    'message' => 'Vous ne pouvez créer des mouvements que pour votre campus',
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            // Référence unique
            $prefix = $validated['type'] === 'income' ? 'CAI' : 'CDE';
            $reference = $prefix . '-' . now()->format('Ymd') . '-'
                       . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            $movement = CashMovement::create([
                'campus_id'   => $validated['campus_id'],
                'type'        => $validated['type'],
                'category'    => $validated['category'],
                'amount'      => $validated['amount'],
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'reference'   => $reference,
                'created_by'  => $user->id,
            ]);

            // ✅ Pièce jointe
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $folder = 'cash/' . now()->format('Y/m');
                $extension = $file->getClientOriginalExtension();
                $filename = $movement->id . '-' . $reference . '.' . $extension;

                $path = $file->storeAs($folder, $filename, 'private');

                $movement->update([
                    'attachment_path' => $path,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getMimeType(),
                    'attachment_size' => $file->getSize(),
                ]);
            }

            // ✅ Log
            $typeLabel = $validated['type'] === 'income' ? 'Entrée' : 'Sortie';
            $categoryLabel = $config[$validated['category']];

            $this->activityLogService->log(
                action: 'created',
                targetType: 'cash_movement',
                targetId: $movement->id,
                targetName: $reference,
                newData: $movement->toArray(),
                changes: "Nouvelle {$typeLabel} de caisse ({$categoryLabel}) de "
                        . number_format($validated['amount'], 0, ',', ' ')
                        . " FCFA : {$validated['title']}",
                campusId: $validated['campus_id']
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('CashMovementController@store', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => "Erreur lors de l'enregistrement : " . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Mouvement de caisse enregistré avec succès',
            'data'    => $movement->load(['campus', 'createdBy']),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ SUPPRIMER UN MOUVEMENT ═══
    // ═══════════════════════════════════════════════════════════
    public function destroy(Request $request, int $id)
    {
        $movement = CashMovement::findOrFail($id);

        // ✅ Policy : refuse automatiquement les secrétaires
        $this->authorize('delete', $movement);

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Log AVANT suppression
            $typeLabel = $movement->type === 'income' ? 'entrée' : 'sortie';
            $this->activityLogService->log(
                action: 'deleted',
                targetType: 'cash_movement',
                targetId: $movement->id,
                targetName: $movement->reference,
                oldData: $movement->toArray(),
                changes: 'Suppression ' . $typeLabel . ' de caisse '
                        . $movement->reference . ' de '
                        . number_format($movement->amount, 0, ',', ' ') . ' FCFA',
                campusId: $movement->campus_id
            );

            // Supprimer la PJ physique
            if ($movement->attachment_path && Storage::disk('private')->exists($movement->attachment_path)) {
                Storage::disk('private')->delete($movement->attachment_path);
            }

            // Soft delete
            $movement->deleted_by = $user->id;
            $movement->save();
            $movement->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('CashMovementController@destroy', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }

        return response()->json(['message' => 'Mouvement supprimé avec succès']);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ TÉLÉCHARGER LA PIÈCE JOINTE ═══
    // ═══════════════════════════════════════════════════════════
    public function downloadAttachment(Request $request, int $id)
    {
        $movement = CashMovement::findOrFail($id);

        // ✅ Policy : secrétaire = uniquement le sien / admin = son campus
        $this->authorize('view', $movement);

        if (!$movement->attachment_path || !Storage::disk('private')->exists($movement->attachment_path)) {
            return response()->json(['message' => 'Aucune pièce jointe'], 404);
        }

        $file = Storage::disk('private')->get($movement->attachment_path);
        $mime = $movement->attachment_mime ?? 'application/octet-stream';
        $name = $movement->attachment_name ?? 'piece-jointe';

        return response($file, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $name . '"');
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ RÉSUMÉ (KPIs) ═══
    // ═══════════════════════════════════════════════════════════
    public function summary(Request $request)
    {
        $this->authorize('viewAny', CashMovement::class);

        $user = $request->user();
        $now = now();

        $query = CashMovement::query();

        // ─── SCOPE PAR RÔLE ────────────────────────────────
        if ($user->role === 'secretary') {
            // ✅ Secrétaire : ses propres mouvements uniquement
            $query->where('created_by', $user->id)->where('campus_id', $user->campus_id);
        } elseif ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        } elseif (in_array($user->role, ['super_admin', 'admin_global']) && $request->filled('campus_id')) {
            $query->where('campus_id', $request->integer('campus_id'));
        }

        // Totaux
        $todayIncome   = (clone $query)->where('type', 'income')->whereDate('created_at', $now->toDateString())->sum('amount');
        $todayExpense  = (clone $query)->where('type', 'expense')->whereDate('created_at', $now->toDateString())->sum('amount');

        $monthIncome   = (clone $query)->where('type', 'income')->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('amount');
        $monthExpense  = (clone $query)->where('type', 'expense')->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('amount');

        $yearIncome    = (clone $query)->where('type', 'income')->whereYear('created_at', $now->year)->sum('amount');
        $yearExpense   = (clone $query)->where('type', 'expense')->whereYear('created_at', $now->year)->sum('amount');

        $totalIncome   = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense  = (clone $query)->where('type', 'expense')->sum('amount');

        return response()->json([
            'data' => [
                'today' => [
                    'income'  => (float) $todayIncome,
                    'expense' => (float) $todayExpense,
                    'balance' => (float) ($todayIncome - $todayExpense),
                ],
                'month' => [
                    'income'  => (float) $monthIncome,
                    'expense' => (float) $monthExpense,
                    'balance' => (float) ($monthIncome - $monthExpense),
                ],
                'year' => [
                    'income'  => (float) $yearIncome,
                    'expense' => (float) $yearExpense,
                    'balance' => (float) ($yearIncome - $yearExpense),
                ],
                'total' => [
                    'income'  => (float) $totalIncome,
                    'expense' => (float) $totalExpense,
                    'balance' => (float) ($totalIncome - $totalExpense),
                ],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ CATÉGORIES DISPONIBLES ═══
    // ═══════════════════════════════════════════════════════════
    public function categories()
    {
        return response()->json([
            'data' => config('cash.categories'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ HELPERS ═══
    // ═══════════════════════════════════════════════════════════
    private function resolvePeriod(string $period, Request $request): array
    {
        $today = now()->toDateString();

        return match ($period) {
            'today'      => [$today, $today],
            'week'       => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month'      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'       => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom'     => [
                $request->filled('date_from') ? $request->date('date_from')?->toDateString() : null,
                $request->filled('date_to')   ? $request->date('date_to')?->toDateString()   : null,
            ],
            'all'        => [null, null],
            default      => [$today, $today],
        };
    }
}