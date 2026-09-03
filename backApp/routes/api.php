<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\FormationController;
// use App\Http\Controllers\Api\LogController; // ← Tu peux supprimer cette ligne si ce contrôleur est remplacé
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PaymentController;          // ← AJOUTÉ
use App\Http\Controllers\Api\ActivityLogController;     // ← AJOUTÉ
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    // === BROADCASTING AUTH (Pusher) ===
    Route::post('/broadcasting/auth', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        if (!$socketId || !$channelName) {
            return response()->json(['error' => 'Missing socket_id or channel_name'], 400);
        }

        $pusher = new \Pusher\Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            ['cluster' => env('PUSHER_CLUSTER', 'eu')]
        );

        $channelParts = explode('.', $channelName);
        if (isset($channelParts[1]) && (int) $channelParts[1] !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(
            $pusher->authorizeChannel($channelName, $socketId)
        );
    })->middleware('auth:sanctum');

    // === PUBLIC ===
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/dashboard/stats', [App\Http\Controllers\Api\DashboardController::class, 'getStats']);

    // === PROTÉGÉES ===
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);

        // Utilisateurs
        Route::middleware('role:super_admin,admin_global,admin_campus')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        });

        // Campus
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
        Route::patch('/academic-years/switch', [AcademicYearController::class, 'switchYear']);

        Route::middleware('role:super_admin,admin_global')->group(function () {
            Route::post('/academic-years', [AcademicYearController::class, 'store']);
            Route::put('/academic-years/{id}', [AcademicYearController::class, 'update']);
            Route::delete('/academic-years/{id}', [AcademicYearController::class, 'destroy']);
        });

        // Formations
        Route::get('/formations', [FormationController::class, 'index']);
        Route::get('/formations/{id}', [FormationController::class, 'show']);

        Route::middleware('role:super_admin,admin_global')->group(function () {
            Route::post('/formations', [FormationController::class, 'store']);
            Route::put('/formations/{id}', [FormationController::class, 'update']);
            Route::delete('/formations/{id}', [FormationController::class, 'destroy']);
            Route::patch('/formations/{id}/toggle-status', [FormationController::class, 'toggleStatus']);
        });

        // ═══ INSCRIPTIONS (CRUD nettoyé) ═══
        // Note : updateAmount et logs/all ont été retirés car déplacés vers leurs contrôleurs dédiés
        Route::get('/registrations', [RegistrationController::class, 'index']);
        Route::get('/registrations/{id}', [RegistrationController::class, 'show']);
        Route::post('/registrations', [RegistrationController::class, 'store']);
         Route::delete('/registrations/{id}', [RegistrationController::class, 'destroy']);
         Route::get('/registrations/{id}/form', [RegistrationController::class, 'generateForm']);
        Route::get('/registrations/stats/{campusId}', [RegistrationController::class, 'stats']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/search', [PaymentController::class, 'searchStudents']);
        Route::get('/payments/{id}/receipt', [PaymentController::class, 'generateReceipt']);

        // ═══ PAIEMENTS (Nouveau contrôleur dédié) ═══
        // Ajouter un nouveau versement à une inscription
        Route::post('/registrations/{registrationId}/payments', [PaymentController::class, 'store']);
        
        // Modifier, supprimer (soft delete) ou restaurer un paiement existant
         Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
 
        // ═══ LOGS D'ACTIVITÉ (Contrôleur dédié en lecture seule) ═══
        Route::middleware('role:super_admin,admin_global,admin_campus')->group(function () {
            Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        });

        // (Optionnel) Si l'ancien LogController ne sert plus à rien, tu peux supprimer cette ligne :
        // Route::get('/logs', [LogController::class, 'index']);

        // === NOTIFICATIONS ===
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

 
                // ═══ ÉTUDIANTS (Lecture + Modification infos personnelles uniquement) ═══
        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/{id}', [StudentController::class, 'show']);
        Route::put('/students/{id}', [StudentController::class, 'update']);
        Route::get('/students/{id}/photo', [StudentController::class, 'getPhoto']);

                // ═══ DÉPENSES ═══
        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::get('/expenses/summary', [ExpenseController::class, 'summary']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

                // ═══ MOUVEMENTS FINANCIERS (Bilan comptable) ═══
        Route::get('/financial-movements', [App\Http\Controllers\Api\FinancialMovementController::class, 'index']);
        Route::get('/financial-movements/report', [App\Http\Controllers\Api\FinancialMovementController::class, 'generateReport']);
    });
});