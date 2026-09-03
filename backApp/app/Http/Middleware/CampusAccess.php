<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CampusAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->user()->role, ['super_admin', 'admin_global'])) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        return $next($request);
    }
}