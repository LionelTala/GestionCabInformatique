<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Public
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protégées
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
            

        // Campus (seul Super Admin & Admin Global)
        Route::middleware('campus.access')->group(function () {
            Route::get('/campuses', [CampusController::class, 'index']);
            Route::get('/campuses/{id}', [CampusController::class, 'show']);
            Route::post('/campuses', [CampusController::class, 'store']);
            Route::put('/campuses/{id}', [CampusController::class, 'update']);
            Route::delete('/campuses/{id}', [CampusController::class, 'destroy']);
            Route::patch('/campuses/{id}/toggle-status', [CampusController::class, 'toggleStatus']);
        });
        // Années scolaires
        Route::get('/academic-years', [AcademicYearController::class, 'index']);
        Route::get('/academic-years/{id}', [AcademicYearController::class, 'show']);
        Route::post('/academic-years', [AcademicYearController::class, 'store']);
        Route::put('/academic-years/{id}', [AcademicYearController::class, 'update']);
        Route::delete('/academic-years/{id}', [AcademicYearController::class, 'destroy']);
        Route::patch('/academic-years/switch', [AcademicYearController::class, 'switchYear']);

        //Formations
        Route::get('/formations', [FormationController::class, 'index']);
        Route::get('/formations/{id}', [FormationController::class, 'show']);
        Route::post('/formations', [FormationController::class, 'store']);
        Route::put('/formations/{id}', [FormationController::class, 'update']);
        Route::delete('/formations/{id}', [FormationController::class, 'destroy']);
        Route::patch('/formations/{id}/toggle-status', [FormationController::class, 'toggleStatus']);
    });
});
