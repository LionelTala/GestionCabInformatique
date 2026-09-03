<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Liste paginée des logs d'activité.
     * Route : GET /api/activity-logs
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Vérification des droits (seuls les admins peuvent voir les logs)
        if (!in_array($user->role, ['super_admin', 'admin_global', 'admin_campus'])) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $query = ActivityLog::with(['user', 'campus'])
            ->orderBy('created_at', 'desc');

        // Restriction campus pour admin_campus
        if ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        // Filtres optionnels
        if ($request->has('campus_id')) {
            $query->where('campus_id', $request->campus_id);
        }
        if ($request->has('target_type')) {
            $query->where('target_type', $request->target_type);
        }
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Pagination Laravel standard (retourne { data: [...], current_page: ..., total: ..., ... })
        return response()->json($query->paginate($request->integer('per_page', 30)));
    }
}