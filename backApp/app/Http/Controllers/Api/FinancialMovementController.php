<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialMovementController extends Controller
{
    /**
     * Liste des mouvements financiers (dépenses + revenus)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Vérifier les droits d'accès
        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $query = FinancialTransaction::with(['campus', 'registration.student', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Restriction pour admin_campus
        if ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        // Filtres
        if ($request->filled('campus_id') && $user->role !== 'admin_campus') {
            $campusId = filter_var($request->campus_id, FILTER_VALIDATE_INT);
            if ($campusId !== false) {
                $query->where('campus_id', $campusId);
            }
        }

        if ($request->filled('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filtre par période
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

        // Filtre par mois/année spécifiques
        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }
        if ($request->filled('year')) {
            $year = filter_var($request->year, FILTER_VALIDATE_INT);
            if ($year !== false) {
                $query->whereYear('created_at', $year);
            }
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $movements = $query->paginate(20);

        // Calculer les totaux
        $totalIncome = (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        return response()->json([
            'data' => $movements,
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'balance' => $balance,
            ]
        ]);
    }

    /**
     * Générer le rapport PDF
     */
        public function generateReport(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $query = FinancialTransaction::with(['campus', 'registration.student', 'createdBy'])
            ->orderBy('campus_id')
            ->orderBy('created_at', 'asc');

        if ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        if ($request->filled('campus_id') && $user->role !== 'admin_campus') {
            $campusId = filter_var($request->campus_id, FILTER_VALIDATE_INT);
            if ($campusId !== false) {
                $query->where('campus_id', $campusId);
            }
        }

        if ($request->filled('type') && in_array($request->type, ['income', 'expense'])) {
            $query->where('type', $request->type);
        }

        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }
        if ($request->filled('year')) {
            $year = filter_var($request->year, FILTER_VALIDATE_INT);
            if ($year !== false) {
                $query->whereYear('created_at', $year);
            }
        }

        $movements = $query->get();

        $totalIncome = $movements->where('type', 'income')->sum('amount');
        $totalExpense = $movements->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        $groupedByCampus = $movements->groupBy('campus_id')->map(function ($items) {
            return [
                'campus' => $items->first()->campus,
                'movements' => $items,
                'income' => $items->where('type', 'income')->sum('amount'),
                'expense' => $items->where('type', 'expense')->sum('amount'),
                'balance' => $items->where('type', 'income')->sum('amount') - $items->where('type', 'expense')->sum('amount'),
            ];
        });

        $filterInfo = $this->buildFilterInfo($request, $user);

        // ✅ Charger le logo en base64 pour DomPDF
        $logoBase64 = null;
        $logoPath = public_path('logo.jpg');
        if (file_exists($logoPath)) {
            $imageData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/jpeg;base64,' . $imageData;
        }

        $pdf = Pdf::loadView('pdfs.financial_report', [
            'groupedByCampus' => $groupedByCampus,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'balance' => $balance,
            'filterInfo' => $filterInfo,
            'generatedAt' => now(),
            'generatedBy' => $user->first_name . ' ' . $user->last_name,
            'logoBase64' => $logoBase64, // ✅ Passer le logo à la vue
        ]);

        $filename = 'rapport-financier-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($filename);
    }
    /**
     * Construire les informations de filtre pour le titre du rapport
     */
    private function buildFilterInfo(Request $request, $user): string
    {
        $parts = [];

        // Campus
        if ($user->role === 'admin_campus') {
            $campus = \App\Models\Campus::find($user->campus_id);
            $parts[] = 'Campus : ' . ($campus->name ?? 'N/A');
        } elseif ($request->filled('campus_id')) {
            $campus = \App\Models\Campus::find($request->campus_id);
            $parts[] = 'Campus : ' . ($campus->name ?? 'N/A');
        } else {
            $parts[] = 'Tous les campus';
        }

        // Période
        if ($request->filled('month') && $request->filled('year')) {
            $monthNames = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ];
            $parts[] = $monthNames[(int)$request->month] . ' ' . $request->year;
        } elseif ($request->filled('year')) {
            $parts[] = 'Année ' . $request->year;
        } else {
            $parts[] = 'Toutes les périodes';
        }

        return implode(' | ', $parts);
    }
}