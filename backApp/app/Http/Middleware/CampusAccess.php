<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class CampusAccess
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Vous devez être authentifié pour accéder à cette ressource'
                ], 401);
            }

            // Seul Super Admin et Admin Global peuvent gérer les campus
            if (!in_array($user->role, ['super_admin', 'admin_global'])) {
                return response()->json([
                    'message' => 'Accès non autorisé. Seul un Super Admin ou un Admin Global peut gérer les campus.'
                ], 403);
            }

            return $next($request);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la vérification des droits d\'accès',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}