<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Api\DocumentVerificationController;

// ═══════════════════════════════════════════════════════════
// 1. ROUTES SPÉCIFIQUES (PRIORITÉ HAUTE)
// ═══════════════════════════════════════════════════════════

// ✅ Vérification de document (accessible sans authentification)
Route::get('/verify-document', [DocumentVerificationController::class, 'verify'])
    ->name('document.verify');

// Sanctum CSRF
Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
})->middleware('web');

// ═══════════════════════════════════════════════════════════
// 2. FALLBACK SPA (TOUJOURS EN DERNIER)
// ═══════════════════════════════════════════════════════════

Route::fallback(function () {
    // ✅ Ne PAS servir index.html pour les routes API
    if (request()->is('api/*')) {
        return response()->json(['message' => 'Route API introuvable'], 404);
    }

    $indexPath = public_path('index.html');

    if (!File::exists($indexPath)) {
        abort(500, "index.html introuvable dans public/. Vérifie le build Angular.");
    }

    return response(File::get($indexPath), 200)
        ->header('Content-Type', 'text/html');
});