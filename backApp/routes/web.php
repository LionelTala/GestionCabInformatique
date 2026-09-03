<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\File;
 
 use App\Http\Controllers\Api\DocumentVerificationController;

// Route publique de vérification de document (accessible sans authentification)
Route::get('/verify-document', [DocumentVerificationController::class, 'verify']);

Route::get('/{any?}', function () {
    return File::get(public_path('index.html'));
})->where('any', '^(?!api|js|css|img|assets|favicon\.ico|\b.*\.[a-zA-Z0-9]+$).*$');

Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
})->middleware('web');