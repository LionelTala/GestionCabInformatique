<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        // ✅ 1. Résolution de la période (par défaut : aujourd'hui)
        $period = $request->get('period', 'today');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $request);

        $query = ActivityLog::with(['user:id,first_name,last_name,email,role'])
            ->orderBy('created_at', 'desc');

        // ✅ 2. Scope par rôle
        if ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        // ✅ 3. Filtre période
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        // ✅ 4. Filtres classiques
        if ($request->filled('campus_id') && in_array($user->role, ['super_admin', 'admin_global'])) {
            $query->where('campus_id', $request->campus_id);
        }
        if ($request->filled('user_id'))     $query->where('user_id', $request->user_id);
        if ($request->filled('action'))      $query->where('action', $request->action);
        if ($request->filled('target_type')) $query->where('target_type', $request->target_type);

        // ✅ 5. Stats de la période
        $totalCount = (clone $query)->count();

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 30)),
            'meta' => [
                'period'      => $period,
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'total_count' => $totalCount,
            ],
        ]);
    }

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
}