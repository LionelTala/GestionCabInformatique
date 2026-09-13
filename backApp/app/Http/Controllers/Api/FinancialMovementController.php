<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\Campus;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialMovementController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // ═══ LISTE DES MOUVEMENTS FINANCIERS ═══
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        // ✅ 1. Résolution de la période (par défaut : aujourd'hui)
        $period = $request->get('period', 'today');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $request);

        // ✅ 2. Scope par rôle
        $campusScope = function ($query) use ($user, $request) {
            if ($user->role === 'admin_campus') {
                $query->where('campus_id', $user->campus_id);
            } elseif ($request->filled('campus_id')) {
                $campusId = filter_var($request->campus_id, FILTER_VALIDATE_INT);
                if ($campusId !== false) {
                    $query->where('campus_id', $campusId);
                }
            }
        };

        // ═══════════════════════════════════════════════════════════
        // A. REQUÊTE PRINCIPALE (avec période)
        // ═══════════════════════════════════════════════════════════
        $query = FinancialTransaction::with(['campus', 'registration.student', 'createdBy'])
            ->orderBy('created_at', 'desc');

        $campusScope($query);

        // ✅ Filtre période (LE CŒUR DU FIX)
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        // Filtres classiques
        if ($request->filled('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('type', $request->type);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        // ─── SOLDE DE LA PÉRIODE ───────────────────────────
        $periodIncome  = (clone $query)->where('type', 'income')->sum('amount');
        $periodExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $periodBalance = $periodIncome - $periodExpense;

        // Pagination
        $movements = $query->paginate($request->integer('per_page', 20));

        // ═══════════════════════════════════════════════════════════
        // B. SOLDE GLOBAL (toutes périodes, même scope campus)
        // ═══════════════════════════════════════════════════════════
        $globalQuery = FinancialTransaction::query();
        $campusScope($globalQuery);

        $globalIncome  = (clone $globalQuery)->where('type', 'income')->sum('amount');
        $globalExpense = (clone $globalQuery)->where('type', 'expense')->sum('amount');
        $globalBalance = $globalIncome - $globalExpense;

        // ═══════════════════════════════════════════════════════════
        // C. RÉPONSE
        // ═══════════════════════════════════════════════════════════
        return response()->json([
            'data' => $movements,
            'summary' => [
                // ✅ Solde de la période sélectionnée
                'period' => [
                    'income'  => (float) $periodIncome,
                    'expense' => (float) $periodExpense,
                    'balance' => (float) $periodBalance,
                ],
                // ✅ Solde global (depuis le début)
                'global' => [
                    'income'  => (float) $globalIncome,
                    'expense' => (float) $globalExpense,
                    'balance' => (float) $globalBalance,
                ],
            ],
            'meta' => [
                'period'      => $period,
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'total_count' => $movements->total(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ RAPPORT PDF ═══
    // ═══════════════════════════════════════════════════════════
    public function generateReport(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        // ✅ Résolution de période
        $period = $request->get('period', 'today');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $request);

        $query = FinancialTransaction::with(['campus', 'registration.student', 'createdBy'])
            ->orderBy('campus_id')
            ->orderBy('created_at', 'asc');

        // Scope
        if ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        } elseif ($request->filled('campus_id')) {
            $campusId = filter_var($request->campus_id, FILTER_VALIDATE_INT);
            if ($campusId !== false) {
                $query->where('campus_id', $campusId);
            }
        }

        if ($request->filled('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('type', $request->type);
        }

        // ✅ Filtre période
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        $movements = $query->get();

        $totalIncome  = $movements->where('type', 'income')->sum('amount');
        $totalExpense = $movements->where('type', 'expense')->sum('amount');
        $balance      = $totalIncome - $totalExpense;

        $groupedByCampus = $movements->groupBy('campus_id')->map(function ($items) {
            return [
                'campus'    => $items->first()->campus,
                'movements' => $items,
                'income'    => $items->where('type', 'income')->sum('amount'),
                'expense'   => $items->where('type', 'expense')->sum('amount'),
                'balance'   => $items->where('type', 'income')->sum('amount')
                             - $items->where('type', 'expense')->sum('amount'),
            ];
        });

        $filterInfo = $this->buildFilterInfo($request, $user, $period, $dateFrom, $dateTo);

        // Logo base64 pour DomPDF
        $logoBase64 = null;
        $logoPath = public_path('logo.jpg');
        if (file_exists($logoPath)) {
            $imageData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/jpeg;base64,' . $imageData;
        }

        $pdf = Pdf::loadView('pdfs.financial_report', [
            'groupedByCampus' => $groupedByCampus,
            'totalIncome'     => $totalIncome,
            'totalExpense'    => $totalExpense,
            'balance'         => $balance,
            'filterInfo'      => $filterInfo,
            'generatedAt'     => now(),
            'generatedBy'     => $user->first_name . ' ' . $user->last_name,
            'logoBase64'      => $logoBase64,
            'period'          => $period,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
        ]);

        $filename = 'rapport-financier-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($filename);
    }

    // ═══════════════════════════════════════════════════════════
    // ═══ HELPERS ═══
    // ═══════════════════════════════════════════════════════════

    /**
     * ✅ Résout la période demandée en [dateFrom, dateTo]
     */
    private function resolvePeriod(string $period, Request $request): array
    {
        $today = now()->toDateString();

        return match ($period) {
            'today'  => [$today, $today],
            'week'   => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month'  => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'   => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom' => [
                $request->filled('date_from') ? $request->date('date_from')?->toDateString() : null,
                $request->filled('date_to')   ? $request->date('date_to')?->toDateString()   : null,
            ],
            'all'    => [null, null],
            default  => [$today, $today],
        };
    }

    /**
     * Construire les informations de filtre pour le titre du rapport
     */
    private function buildFilterInfo(Request $request, $user, string $period, ?string $dateFrom, ?string $dateTo): string
    {
        $parts = [];

        // Campus
        if ($user->role === 'admin_campus') {
            $campus = Campus::find($user->campus_id);
            $parts[] = 'Campus : ' . ($campus->name ?? 'N/A');
        } elseif ($request->filled('campus_id')) {
            $campus = Campus::find($request->campus_id);
            $parts[] = 'Campus : ' . ($campus->name ?? 'N/A');
        } else {
            $parts[] = 'Tous les campus';
        }

        // Période
        $periodLabels = [
            'today'  => 'Aujourd\'hui',
            'week'   => 'Cette semaine',
            'month'  => 'Ce mois',
            'year'   => 'Cette année',
            'all'    => 'Toutes les périodes',
        ];

        if ($period === 'custom' && $dateFrom && $dateTo) {
            $parts[] = 'Du ' . date('d/m/Y', strtotime($dateFrom)) . ' au ' . date('d/m/Y', strtotime($dateTo));
        } elseif (isset($periodLabels[$period])) {
            $parts[] = $periodLabels[$period];
        } else {
            $parts[] = 'Toutes les périodes';
        }

        return implode(' | ', $parts);
    }
}