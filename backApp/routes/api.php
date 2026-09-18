<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\FinancialMovementController;
use App\Http\Controllers\Api\AttestationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\DashboardController;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════
// ROUTE USER (auth)
// ═══════════════════════════════════════════════════════════
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ═══════════════════════════════════════════════════════════
// ✅ ROUTES PDF SIGNÉES (hors préfixe API)
// → Middleware 'web' pour les cookies de session
// → Middleware 'signed' pour la signature d'URL
// ═══════════════════════════════════════════════════════════


// ═══════════════════════════════════════════════════════════
// API v1
// ═══════════════════════════════════════════════════════════
Route::prefix('v1')->group(function () {
    Route::middleware([ 'auth:sanctum'])->group(function () {
    Route::get('/pdf/receipt/{id}', [PaymentController::class, 'downloadReceipt'])
        ->name('payments.receipt.download');

    Route::get('/pdf/registration/{id}', [RegistrationController::class, 'downloadForm'])
        ->name('registrations.form.download');
});

    // ─────────────────────────────────────────────────────
    // BROADCASTING AUTH (Pusher)
    // ─────────────────────────────────────────────────────
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

    // ─────────────────────────────────────────────────────
    // ROUTES PUBLIQUES
    // ─────────────────────────────────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login']);

    // ─────────────────────────────────────────────────────
    // ROUTES PROTÉGÉES
    // ─────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // ═══ AUTH ═══
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // ═══ DASHBOARD ═══
        Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

        // ═══════════════════════════════════════════════════
        // ═══ PROFIL ═══
        // ═══════════════════════════════════════════════════
        Route::prefix('profile')->group(function () {
            Route::get('/',              [ProfileController::class, 'show']);
            Route::put('/',              [ProfileController::class, 'update']);
            Route::patch('/password',    [ProfileController::class, 'updatePassword']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ UTILISATEURS ═══
        // ═══════════════════════════════════════════════════
        Route::middleware('role:super_admin,admin_global,admin_campus')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ ATTESTATIONS ═══
        // ═══════════════════════════════════════════════════
        Route::prefix('attestations')->group(function () {
            // ✅ Routes statiques AVANT dynamiques
            Route::get('/search-students', [AttestationController::class, 'searchStudents']);
            Route::get('/stats',           [AttestationController::class, 'stats']);

            // Routes dynamiques
            Route::get('/',                [AttestationController::class, 'index']);
            Route::post('/',               [AttestationController::class, 'store']);
            Route::patch('/{id}/settle',   [AttestationController::class, 'settle']);
            Route::patch('/{id}/unsettle', [AttestationController::class, 'unsettle']);
            Route::delete('/{id}',         [AttestationController::class, 'cancel']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ CAMPUS ═══
        // ═══════════════════════════════════════════════════
        Route::middleware('campus.access')->group(function () {
            Route::get('/campuses', [CampusController::class, 'index']);
            Route::get('/campuses/{id}', [CampusController::class, 'show']);
            Route::post('/campuses', [CampusController::class, 'store']);
            Route::put('/campuses/{id}', [CampusController::class, 'update']);
            Route::delete('/campuses/{id}', [CampusController::class, 'destroy']);
            Route::patch('/campuses/{id}/toggle-status', [CampusController::class, 'toggleStatus']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ ANNÉES SCOLAIRES ═══
        // ═══════════════════════════════════════════════════
        Route::get('/academic-years', [AcademicYearController::class, 'index']);
        Route::get('/academic-years/{id}', [AcademicYearController::class, 'show']);
        Route::patch('/academic-years/switch', [AcademicYearController::class, 'switchYear']);

        Route::middleware('role:super_admin,admin_global')->group(function () {
            Route::post('/academic-years', [AcademicYearController::class, 'store']);
            Route::put('/academic-years/{id}', [AcademicYearController::class, 'update']);
            Route::delete('/academic-years/{id}', [AcademicYearController::class, 'destroy']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ FORMATIONS ═══
        // ═══════════════════════════════════════════════════
        Route::get('/formations', [FormationController::class, 'index']);
        Route::get('/formations/{id}', [FormationController::class, 'show']);

        Route::middleware('role:super_admin,admin_global,admin_campus,secretary')->group(function () {
            Route::post('/formations', [FormationController::class, 'store']);
            Route::put('/formations/{id}', [FormationController::class, 'update']);
            Route::delete('/formations/{id}', [FormationController::class, 'destroy']);
            Route::patch('/formations/{id}/toggle-status', [FormationController::class, 'toggleStatus']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ INSCRIPTIONS ═══
        // ═══════════════════════════════════════════════════
        Route::get('/registrations', [RegistrationController::class, 'index']);
        Route::get('/registrations/{id}', [RegistrationController::class, 'show']);
        Route::post('/registrations', [RegistrationController::class, 'store']);
        Route::delete('/registrations/{id}', [RegistrationController::class, 'destroy']);
        Route::get('/registrations/stats/{campusId}', [RegistrationController::class, 'stats']);

        // ✅ Génération de l'URL signée (authentifié par cookie)
        Route::get('/registrations/{id}/form-url', [RegistrationController::class, 'getFormDownloadUrl']);

        // ═══════════════════════════════════════════════════
        // ═══ PAIEMENTS ═══
        // ═══════════════════════════════════════════════════
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/search', [PaymentController::class, 'searchStudents']);
        Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);

        // ✅ Génération de l'URL signée (authentifié par cookie)
        Route::get('/payments/{id}/receipt-url', [PaymentController::class, 'getReceiptDownloadUrl']);

        // Ajouter un versement à une inscription
        Route::post('/registrations/{registrationId}/payments', [PaymentController::class, 'store']);

        // ═══════════════════════════════════════════════════
        // ═══ ÉTUDIANTS ═══
        // ═══════════════════════════════════════════════════
        // Rapports (AVANT /{id} pour éviter conflit)
        Route::get('/students/scholarship-report', [StudentController::class, 'scholarshipReport']);
        Route::get('/students/scholarship-report/pdf', [StudentController::class, 'generateScholarshipReport']);
        Route::get('/students/simple-list', [StudentController::class, 'simpleList']);
        Route::get('/students/simple-list/pdf', [StudentController::class, 'generateSimpleList']);

        // CRUD étudiants
        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/{id}', [StudentController::class, 'show']);
        Route::put('/students/{id}', [StudentController::class, 'update']);
        Route::get('/students/{id}/photo', [StudentController::class, 'getPhoto']);

        // ═══════════════════════════════════════════════════
        // ═══ LOGS D'ACTIVITÉ ═══
        // ═══════════════════════════════════════════════════
        Route::middleware('role:super_admin,admin_global,admin_campus')->group(function () {
            Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ NOTIFICATIONS ═══
        // ═══════════════════════════════════════════════════
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

        // ═══════════════════════════════════════════════════
        // ═══ CAISSE (Cash Movements) ═══
        // ═══════════════════════════════════════════════════
        Route::prefix('cash-movements')->group(function () {
            // ⚠️ Routes statiques AVANT /{id}
            Route::get('/summary',    [CashMovementController::class, 'summary']);
            Route::get('/categories', [CashMovementController::class, 'categories']);

            Route::get('/',           [CashMovementController::class, 'index']);
            Route::post('/',          [CashMovementController::class, 'store']);
            Route::delete('/{id}',    [CashMovementController::class, 'destroy']);
            Route::get('/{id}/attachment', [CashMovementController::class, 'downloadAttachment']);
        });

        // ═══════════════════════════════════════════════════
        // ═══ MOUVEMENTS FINANCIERS ═══
        // ═══════════════════════════════════════════════════
        Route::get('/financial-movements',        [FinancialMovementController::class, 'index']);
        Route::get('/financial-movements/report', [FinancialMovementController::class, 'generateReport']);
    });
});