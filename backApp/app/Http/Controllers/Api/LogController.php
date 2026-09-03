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

        $query = ActivityLog::with(['user:id,first_name,last_name,email,role'])
            ->orderBy('created_at', 'desc');

        if ($user->role === 'admin_campus') {
            $query->where('campus_id', $user->campus_id);
        }

        if ($request->campus_id)   $query->where('campus_id', $request->campus_id);
        if ($request->user_id)     $query->where('user_id', $request->user_id);
        if ($request->action)      $query->where('action', $request->action);
        if ($request->target_type) $query->where('target_type', $request->target_type);

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 30)),
        ]);
    }
}