<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpenseController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    // ═══ LISTE DES DÉPENSES ═══
        public function index(Request $request)
    {
        $user = $request->user();

        $query = FinancialTransaction::with(['campus', 'createdBy'])
            ->where('type', 'expense');

        // ── RESTRICTIONS DE VISIBILITÉ ──
        if ($user->role === 'secretary') {
            $query->where('created_by', $user->id);
        } elseif ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        // ── FILTRES ──
        
        // ✅ CORRECTION ULTRA-ROBUSTE : Cast en entier + vérification stricte
        if ($request->has('campus_id')) {
            $campusId = filter_var($request->campus_id, FILTER_VALIDATE_INT);
            if ($campusId !== false && in_array($user->role, ['super_admin', 'admin_global'])) {
                $query->where('campus_id', $campusId);
            }
        }

        if ($request->filled('category') && in_array($request->category, ['salary', 'other'])) {
            $query->where('category', $request->category);
        }

        if ($request->has('year')) {
            $year = filter_var($request->year, FILTER_VALIDATE_INT);
            if ($year !== false) {
                $query->whereYear('created_at', $year);
            }
        }

        if ($request->filled('period')) {
            $now = now();
            switch ($request->period) {
                case 'today':
                    $query->whereDate('created_at', $now->toDateString());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [
                        $now->copy()->startOfWeek()->toDateTimeString(),
                        $now->copy()->endOfWeek()->toDateTimeString()
                    ]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', $now->month)
                          ->whereYear('created_at', $now->year);
                    break;
                case 'last_month':
                    $lastMonth = $now->copy()->subMonth();
                    $query->whereMonth('created_at', $lastMonth->month)
                          ->whereYear('created_at', $lastMonth->year);
                    break;
                case 'this_year':
                    $query->whereYear('created_at', $now->year);
                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $expenses = $query->orderBy('created_at', 'desc')->paginate(15);
        $totalAmount = (clone $query)->sum('amount');

        return response()->json([
            'data' => $expenses,
            'total_amount' => $totalAmount,
        ]);
    }

    // ═══ CRÉER UNE DÉPENSE ═══
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'campus_id'   => 'required|exists:campuses,id',
            'category'    => 'required|in:salary,other',
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
        ]);

        // La secrétaire ne peut créer des dépenses que pour son propre campus
        if (in_array($user->role, ['admin_campus', 'secretary']) && $validated['campus_id'] != $user->campus_id) {
            return response()->json(['message' => 'Vous ne pouvez créer des dépenses que pour votre campus'], 403);
        }

        DB::beginTransaction();
        try {
            // Générer une référence unique
            $reference = 'DEP-' . now()->format('Ymd') . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);

            $expense = FinancialTransaction::create([
                'registration_id' => null, // Pas lié à une inscription
                'campus_id'       => $validated['campus_id'],
                'type'            => 'expense',
                'category'        => $validated['category'],
                'amount'          => $validated['amount'],
                'description'     => $validated['description'],
                'reference'       => $reference,
                'created_by'      => $user->id,
            ]);

            // LOG
            $categoryLabel = $validated['category'] === 'salary' ? 'Salaire' : 'Autre dépense';
            $this->activityLogService->log(
                action: 'created',
                targetType: 'expense',
                targetId: $expense->id,
                targetName: $reference,
                newData: $expense->toArray(),
                changes: 'Nouvelle dépense (' . $categoryLabel . ') de ' . number_format($validated['amount'], 0, ',', ' ') . ' FCFA : ' . $validated['description'],
                campusId: $validated['campus_id']
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ExpenseController@store', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'enregistrement de la dépense'], 500);
        }

        return response()->json([
            'message' => 'Dépense enregistrée avec succès',
            'data'    => $expense->load(['campus', 'createdBy']),
        ], 201);
    }

    // ═══ SUPPRIMER UNE DÉPENSE ═══
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        // Seuls super_admin et admin_global peuvent supprimer
        if (!in_array($user->role, ['super_admin', 'admin_global'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $expense = FinancialTransaction::where('type', 'expense')->findOrFail($id);

        DB::beginTransaction();
        try {
            $this->activityLogService->log(
                action: 'deleted',
                targetType: 'expense',
                targetId: $expense->id,
                targetName: $expense->reference,
                oldData: $expense->toArray(),
                changes: 'Suppression de la dépense ' . $expense->reference . ' de ' . number_format($expense->amount, 0, ',', ' ') . ' FCFA',
                campusId: $expense->campus_id
            );

            $expense->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ExpenseController@destroy', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }

        return response()->json(['message' => 'Dépense supprimée avec succès']);
    }

    // ═══ RÉSUMÉ FINANCIER (pour les stats en haut de page) ═══
    public function summary(Request $request)
    {
        $user = $request->user();
        $now = now();

        $query = FinancialTransaction::where('type', 'expense');

        // Mêmes restrictions que index()
        if ($user->role === 'secretary') {
            $query->where('created_by', $user->id);
        } elseif ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        if ($request->has('campus_id') && in_array($user->role, ['super_admin', 'admin_global'])) {
            $query->where('campus_id', $request->campus_id);
        }

        // Totaux par période
        $thisMonth = (clone $query)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('amount');
        $lastMonth = (clone $query)->whereMonth('created_at', $now->copy()->subMonth()->month)->whereYear('created_at', $now->copy()->subMonth()->year)->sum('amount');
        $thisYear  = (clone $query)->whereYear('created_at', $now->year)->sum('amount');
        $total     = (clone $query)->sum('amount');

        // Par catégorie
        $salaries = (clone $query)->where('category', 'salary')->sum('amount');
        $others   = (clone $query)->where('category', 'other')->sum('amount');

        return response()->json([
            'data' => [
                'this_month' => $thisMonth,
                'last_month' => $lastMonth,
                'this_year'  => $thisYear,
                'total'      => $total,
                'by_category' => [
                    'salary' => $salaries,
                    'other'  => $others,
                ]
            ]
        ]);
    }
}