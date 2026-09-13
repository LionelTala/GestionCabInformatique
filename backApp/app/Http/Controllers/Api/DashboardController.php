<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Registration;
use App\Models\FinancialTransaction;
use App\Models\CashMovement;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $user = $request->user();
        $now = now();

        // ═══════════════════════════════════════════════════════════
        // 0. SÉCURITÉ : une secrétaire/admin_campus DOIT avoir un campus_id
        // ═══════════════════════════════════════════════════════════
        $isRestrictedByCampus = in_array($user->role, ['admin_campus', 'secretary']);
        $isSecretary          = $user->role === 'secretary';

        if ($isRestrictedByCampus && empty($user->campus_id)) {
            // Sécurité : on bloque complètement
            return response()->json([
                'message' => 'Votre compte n\'est rattaché à aucun campus. Contactez un administrateur.',
            ], 403);
        }

        // ═══════════════════════════════════════════════════════════
        // 1. RÉSOLUTION DE LA PÉRIODE (par défaut : aujourd'hui)
        // ═══════════════════════════════════════════════════════════
        $period = $request->get('period', 'today');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $request);

        // ═══════════════════════════════════════════════════════════
        // 2. HELPERS DE SCOPE (un par ressource)
        // ═══════════════════════════════════════════════════════════

        /**
         * ✅ Scope pour les FinancialTransaction (scolarité)
         * - Secrétaire : UNIQUEMENT ses propres transactions (created_by)
         * - Admin campus : tout son campus
         * - Admin global / super : tout
         */
        $scolarityScope = function ($query) use ($user, $isSecretary, $isRestrictedByCampus) {
            if ($isSecretary) {
                // ✅ SÉCURITÉ MAX : ses propres transactions ET son campus
                $query->where('created_by', $user->id)
                      ->where('campus_id', $user->campus_id);
            } elseif ($isRestrictedByCampus) {
                $query->where('campus_id', $user->campus_id);
            }
            // Sinon (super_admin / admin_global) : pas de filtre
        };

        /**
         * ✅ Scope pour CashMovement (caisse)
         */
        $cashScope = function ($query) use ($user, $isSecretary, $isRestrictedByCampus) {
            if ($isSecretary) {
                $query->where('created_by', $user->id)
                      ->where('campus_id', $user->campus_id);
            } elseif ($isRestrictedByCampus) {
                $query->where('campus_id', $user->campus_id);
            }
        };

        /**
         * ✅ Scope pour Student / Registration (basé sur campus)
         */
        $entityScope = function ($query) use ($user, $isRestrictedByCampus) {
            if ($isRestrictedByCampus) {
                $query->where('campus_id', $user->campus_id);
            }
        };

        // ═══════════════════════════════════════════════════════════
        // 3. STATS PRINCIPALES
        // ═══════════════════════════════════════════════════════════
        $totalStudents = Student::where($entityScope)->count();

        $newRegistrationsQuery = Registration::where($entityScope);
        if ($dateFrom) $newRegistrationsQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $newRegistrationsQuery->whereDate('created_at', '<=', $dateTo);
        $newRegistrations = $newRegistrationsQuery->count();

        $newStudentsQuery = Student::where($entityScope);
        if ($dateFrom) $newStudentsQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $newStudentsQuery->whereDate('created_at', '<=', $dateTo);
        $newStudentsInPeriod = $newStudentsQuery->count();

        // ═══════════════════════════════════════════════════════════
        // 4. SCOLARITÉ — sur la période
        // ═══════════════════════════════════════════════════════════
        $scolarityIncomeQuery = FinancialTransaction::where($scolarityScope)
            ->where('type', 'income');

        $scolarityExpenseQuery = FinancialTransaction::where($scolarityScope)
            ->where('type', 'expense');

        if ($dateFrom) {
            $scolarityIncomeQuery->whereDate('created_at', '>=', $dateFrom);
            $scolarityExpenseQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $scolarityIncomeQuery->whereDate('created_at', '<=', $dateTo);
            $scolarityExpenseQuery->whereDate('created_at', '<=', $dateTo);
        }

        $scolarityIncome  = (float) $scolarityIncomeQuery->sum('amount');
        $scolarityExpense = (float) $scolarityExpenseQuery->sum('amount');
        $scolarityBalance = $scolarityIncome - $scolarityExpense;

        // ═══════════════════════════════════════════════════════════
        // 5. CAISSE — sur la période
        // ═══════════════════════════════════════════════════════════
        $cashBaseQuery = CashMovement::where($cashScope);

        $cashIncomeQuery  = (clone $cashBaseQuery)->where('type', 'income');
        $cashExpenseQuery = (clone $cashBaseQuery)->where('type', 'expense');

        if ($dateFrom) {
            $cashIncomeQuery->whereDate('created_at', '>=', $dateFrom);
            $cashExpenseQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $cashIncomeQuery->whereDate('created_at', '<=', $dateTo);
            $cashExpenseQuery->whereDate('created_at', '<=', $dateTo);
        }

        $cashIncome  = (float) $cashIncomeQuery->sum('amount');
        $cashExpense = (float) $cashExpenseQuery->sum('amount');
        $cashBalance = $cashIncome - $cashExpense;

        // ═══════════════════════════════════════════════════════════
        // 6. SOLDE TOTAL (scolarité + caisse, TOUTES périodes)
        // ═══════════════════════════════════════════════════════════
        $totalScolarityIncome  = (float) FinancialTransaction::where($scolarityScope)->where('type', 'income')->sum('amount');
        $totalScolarityExpense = (float) FinancialTransaction::where($scolarityScope)->where('type', 'expense')->sum('amount');

        $totalCashIncome  = (float) CashMovement::where($cashScope)->where('type', 'income')->sum('amount');
        $totalCashExpense = (float) CashMovement::where($cashScope)->where('type', 'expense')->sum('amount');

        $globalBalance = ($totalScolarityIncome + $totalCashIncome) - ($totalScolarityExpense + $totalCashExpense);

        // ═══════════════════════════════════════════════════════════
        // 7. DERNIERS PAIEMENTS SCOLARITÉ
        // ═══════════════════════════════════════════════════════════
        $recentPaymentsQuery = FinancialTransaction::with(['campus', 'createdBy'])
            ->where($scolarityScope)
            ->where('type', 'income');

        if ($dateFrom) $recentPaymentsQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $recentPaymentsQuery->whereDate('created_at', '<=', $dateTo);

        $recentPayments = $recentPaymentsQuery->latest()->take(5)->get()->map(function ($tx) {
            return [
                'id'          => $tx->id,
                'reference'   => $tx->reference,
                'description' => $tx->description,
                'amount'      => (float) $tx->amount,
                'campus'      => $tx->campus->name ?? '-',
                'created_by'  => $tx->createdBy ? ($tx->createdBy->first_name . ' ' . $tx->createdBy->last_name) : '-',
                'created_at'  => $tx->created_at,
            ];
        });

        // ═══════════════════════════════════════════════════════════
        // 8. DERNIERS MOUVEMENTS DE CAISSE
        // ═══════════════════════════════════════════════════════════
        $recentCashMovementsQuery = CashMovement::with(['campus', 'createdBy'])
            ->where($cashScope);

        if ($dateFrom) $recentCashMovementsQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $recentCashMovementsQuery->whereDate('created_at', '<=', $dateTo);

        $recentCashMovements = $recentCashMovementsQuery->latest()->take(5)->get()->map(function ($m) {
            return [
                'id'          => $m->id,
                'reference'   => $m->reference,
                'title'       => $m->title,
                'description' => $m->description,
                'type'        => $m->type,
                'category'    => $m->category,
                'amount'      => (float) $m->amount,
                'campus'      => $m->campus->name ?? '-',
                'created_by'  => $m->createdBy ? ($m->createdBy->first_name . ' ' . $m->createdBy->last_name) : '-',
                'created_at'  => $m->created_at,
            ];
        });

        // ═══════════════════════════════════════════════════════════
        // 9. CONTRE-ÉCRITURES (annulations)
        // ═══════════════════════════════════════════════════════════
        $recentCancellationsQuery = FinancialTransaction::with(['campus', 'createdBy'])
            ->where($scolarityScope)
            ->where('type', 'expense')
            ->whereIn('category', [
                'tuition_refund',
                'registration_cancel',
                'payment_cancel',
            ]);

        if ($dateFrom) $recentCancellationsQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $recentCancellationsQuery->whereDate('created_at', '<=', $dateTo);

        $recentCancellations = $recentCancellationsQuery->latest()->take(5)->get()->map(function ($tx) {
            return [
                'id'          => $tx->id,
                'reference'   => $tx->reference,
                'description' => $tx->description,
                'category'    => $tx->category,
                'amount'      => (float) $tx->amount,
                'campus'      => $tx->campus->name ?? '-',
                'created_by'  => $tx->createdBy ? ($tx->createdBy->first_name . ' ' . $tx->createdBy->last_name) : '-',
                'created_at'  => $tx->created_at,
            ];
        });

        // ═══════════════════════════════════════════════════════════
        // 10. DERNIÈRES INSCRIPTIONS
        // ═══════════════════════════════════════════════════════════
        $recentActivitiesQuery = Registration::with(['student', 'campus', 'formation'])
            ->where($entityScope);

        if ($dateFrom) $recentActivitiesQuery->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $recentActivitiesQuery->whereDate('created_at', '<=', $dateTo);

        $recentActivities = $recentActivitiesQuery->latest()->take(5)->get()->map(function ($reg) {
            return [
                'id'            => $reg->id,
                'student_name'  => $reg->student->first_name . ' ' . $reg->student->last_name,
                'matricule'     => $reg->student->registration_number,
                'formation'     => $reg->formation->name,
                'campus'        => $reg->campus->name,
                'amount_paid'   => (float) $reg->initial_payment,
                'created_at'    => $reg->created_at,
            ];
        });

        // ═══════════════════════════════════════════════════════════
        // 11. RÉPARTITION PAR CAMPUS (super_admin / admin_global uniquement)
        // ═══════════════════════════════════════════════════════════
        $campusBreakdown = [];
        if (in_array($user->role, ['super_admin', 'admin_global'])) {
            $campusBreakdown = Campus::select('id', 'name', 'city')
                ->withCount('students')
                ->get()
                ->map(function ($campus) use ($dateFrom, $dateTo) {
                    $scolIncomeQ  = FinancialTransaction::where('campus_id', $campus->id)->where('type', 'income');
                    $scolExpenseQ = FinancialTransaction::where('campus_id', $campus->id)->where('type', 'expense');
                    $cashIncomeQ  = CashMovement::where('campus_id', $campus->id)->where('type', 'income');
                    $cashExpenseQ = CashMovement::where('campus_id', $campus->id)->where('type', 'expense');

                    if ($dateFrom) {
                        $scolIncomeQ->whereDate('created_at', '>=', $dateFrom);
                        $scolExpenseQ->whereDate('created_at', '>=', $dateFrom);
                        $cashIncomeQ->whereDate('created_at', '>=', $dateFrom);
                        $cashExpenseQ->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $scolIncomeQ->whereDate('created_at', '<=', $dateTo);
                        $scolExpenseQ->whereDate('created_at', '<=', $dateTo);
                        $cashIncomeQ->whereDate('created_at', '<=', $dateTo);
                        $cashExpenseQ->whereDate('created_at', '<=', $dateTo);
                    }

                    $income  = (float) $scolIncomeQ->sum('amount') + (float) $cashIncomeQ->sum('amount');
                    $expense = (float) $scolExpenseQ->sum('amount') + (float) $cashExpenseQ->sum('amount');

                    return [
                        'id'              => $campus->id,
                        'name'            => $campus->name,
                        'city'            => $campus->city,
                        'student_count'   => $campus->students_count,
                        'period_income'   => $income,
                        'period_expense'  => $expense,
                        'balance'         => $income - $expense,
                    ];
                });
        }

        // ═══════════════════════════════════════════════════════════
        // 12. RÉPONSE FINALE
        // ═══════════════════════════════════════════════════════════
        return response()->json([
            'data' => [
                'total_students'          => $totalStudents,
                'new_students_in_period'  => $newStudentsInPeriod,
                'new_registrations'       => $newRegistrations,

                'scolarity_income'    => $scolarityIncome,
                'scolarity_expense'   => $scolarityExpense,
                'scolarity_balance'   => $scolarityBalance,

                'cash_income'         => $cashIncome,
                'cash_expense'        => $cashExpense,
                'cash_balance'        => $cashBalance,

                'global_income'       => $totalScolarityIncome + $totalCashIncome,
                'global_expense'      => $totalScolarityExpense + $totalCashExpense,
                'global_balance'      => $globalBalance,

                'recent_payments'         => $recentPayments,
                'recent_cash_movements'   => $recentCashMovements,
                'recent_cancellations'    => $recentCancellations,
                'recent_activities'       => $recentActivities,
                'campus_breakdown'        => $campusBreakdown,

                'meta' => [
                    'period'    => $period,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                ],
            ]
        ]);
    }

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
}