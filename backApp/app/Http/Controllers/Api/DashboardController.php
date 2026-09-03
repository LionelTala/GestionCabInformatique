<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Registration;
use App\Models\FinancialTransaction;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
          public function getStats(Request $request)
    {
        $user = $request->user();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // ── 1. PORTÉE SELON LE RÔLE ──
        $scope = function ($query) use ($user) {
            if (in_array($user->role, ['admin_campus', 'secretary'])) {
                $query->where('campus_id', $user->campus_id);
            }
        };

        // ── 2. STATISTIQUES PRINCIPALES ──
        $totalStudents = Student::where($scope)->count();

        $newRegistrations = Registration::where($scope)
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        $monthlyIncome = FinancialTransaction::where($scope)
            ->where('type', 'income')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->sum('amount');

        // ✅ SORTIES (adaptées au rôle)
        $expenseQuery = FinancialTransaction::where('type', 'expense')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear);

        if ($user->role === 'secretary') {
            $expenseQuery->where('created_by', $user->id);
        } elseif ($user->role === 'admin_campus') {
            $expenseQuery->where('campus_id', $user->campus_id);
        }
        // super_admin et admin_global voient tout

        $monthlyExpense = $expenseQuery->sum('amount');

        // ── 3. DERNIERS PAIEMENTS (5 derniers) ──
        $recentPayments = FinancialTransaction::with(['campus', 'createdBy'])
            ->where('type', 'income')
            ->where($scope)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'reference' => $tx->reference,
                    'description' => $tx->description,
                    'amount' => $tx->amount,
                    'campus' => $tx->campus->name ?? '-',
                    'created_by' => $tx->createdBy ? ($tx->createdBy->first_name . ' ' . $tx->createdBy->last_name) : '-',
                    'created_at' => $tx->created_at,
                ];
            });

        // ── 4. DERNIÈRES DÉPENSES (5 dernières, adaptées au rôle) ──
        $recentExpensesQuery = FinancialTransaction::with(['campus', 'createdBy'])
            ->where('type', 'expense')
            ->latest();

        if ($user->role === 'secretary') {
            $recentExpensesQuery->where('created_by', $user->id);
        } elseif ($user->role === 'admin_campus') {
            $recentExpensesQuery->where('campus_id', $user->campus_id);
        }

        $recentExpenses = $recentExpensesQuery->take(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'reference' => $tx->reference,
                    'description' => $tx->description,
                    'category' => $tx->category,
                    'amount' => $tx->amount,
                    'campus' => $tx->campus->name ?? '-',
                    'created_by' => $tx->createdBy ? ($tx->createdBy->first_name . ' ' . $tx->createdBy->last_name) : '-',
                    'created_at' => $tx->created_at,
                ];
            });

        // ── 5. DERNIÈRES INSCRIPTIONS ──
        $recentActivities = Registration::with(['student', 'campus', 'formation'])
            ->where($scope)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($reg) {
                return [
                    'id' => $reg->id,
                    'student_name' => $reg->student->first_name . ' ' . $reg->student->last_name,
                    'matricule' => $reg->student->registration_number,
                    'formation' => $reg->formation->name,
                    'campus' => $reg->campus->name,
                    'amount_paid' => $reg->initial_payment,
                    'created_at' => $reg->created_at,
                ];
            });

        // ── 6. RÉPARTITION PAR CAMPUS (Super Admin / Global uniquement) ──
        $campusBreakdown = [];
        if (in_array($user->role, ['super_admin', 'admin_global'])) {
            $campusBreakdown = Campus::select('id', 'name', 'city')
                ->withCount('students')
                ->get()
                ->map(function ($campus) {
                    $income = FinancialTransaction::where('campus_id', $campus->id)
                        ->where('type', 'income')
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('amount');

                    $expense = FinancialTransaction::where('campus_id', $campus->id)
                        ->where('type', 'expense')
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('amount');

                    return [
                        'id' => $campus->id,
                        'name' => $campus->name,
                        'city' => $campus->city,
                        'student_count' => $campus->students_count,
                        'monthly_income' => $income,
                        'monthly_expense' => $expense,
                        'balance' => $income - $expense,
                    ];
                });
        }

        return response()->json([
            'data' => [
                'total_students' => $totalStudents,
                'new_registrations' => $newRegistrations,
                'monthly_income' => $monthlyIncome,
                'monthly_expense' => $monthlyExpense,
                'recent_payments' => $recentPayments,
                'recent_expenses' => $recentExpenses,
                'recent_activities' => $recentActivities,
                'campus_breakdown' => $campusBreakdown,
            ]
        ]);
    }
}