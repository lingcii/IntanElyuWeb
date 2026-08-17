<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FareDataController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Picto\ArchiveManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProofValidationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TouristSpotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Admin\FeedbackManagementController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ClassificationPointController;
use App\Http\Controllers\VoucherController;

use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
//  Auth (public)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    // Rate limiting — 60 login attempts per minute per IP
    Route::post('/login',    [LoginController::class, 'login'])->middleware('throttle:60,1');
    // 30 registrations per minute per IP
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:30,1');
    // Logout is light — limit to prevent DoS
    Route::post('/logout',   [LogoutController::class, 'logout'])->middleware('throttle:60,1');
    Route::get('/check',     [SessionController::class, 'check']);
});


// ─────────────────────────────────────────────────────────────────────────────
//  Public image serving (no auth required — served to <img> tags in HTML)
// ─────────────────────────────────────────────────────────────────────────────
Route::get('/images/tourist-spots/{filename}', [TouristSpotController::class, 'serveImage']);
Route::get('/serve-image', [TouristSpotController::class, 'serveImageProxy']);
Route::get('/serve-image.php', [TouristSpotController::class, 'serveImageProxy']);
Route::get('/images/proofs/{filename}', [ProofValidationController::class, 'serveImage']);

// ─────────────────────────────────────────────────────────────────────────────
//  Authenticated routes
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('auth.session')->group(function () {

    // Profile (any authenticated role)
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  SHARED TOURIST SPOTS (all roles - PICTO: read-only; LUPTO/MUNICIPAL: full CRUD)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('tourist-spots')->group(function () {
        Route::get('/draft', [TouristSpotController::class, 'getDraft']);
        Route::post('/draft', [TouristSpotController::class, 'saveDraft']);
        Route::delete('/draft/{id}', [TouristSpotController::class, 'deleteDraft']);
        Route::get('/vehicle-types', [TouristSpotController::class, 'getVehicleTypes']);
        Route::get('/', [TouristSpotController::class, 'index']);
        Route::get('/{id}', [TouristSpotController::class, 'show']);
        Route::post('/upload-image', [TouristSpotController::class, 'uploadImage']);
        Route::post('/', [TouristSpotController::class, 'store']);
        Route::put('/{id}', [TouristSpotController::class, 'update']);
        Route::delete('/{id}', [TouristSpotController::class, 'destroy']);
    });

    // Municipalities (shared read)
    Route::get('/municipalities', [MunicipalityController::class, 'index']);
    Route::get('/municipalities/{id}', [MunicipalityController::class, 'show']);

    // Classification Points (shared read)
    Route::get('/classification-points', [ClassificationPointController::class, 'index']);

    // ─────────────────────────────────────────────────────────────────────────
    //  PITCO (picto role)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('pitco')->middleware('role:picto')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/map', [MapController::class, 'luptoMapData']);

        // Tourist Spots (read-only for PICTO)
        Route::get('/tourist-spots', [TouristSpotController::class, 'index']);
        Route::get('/tourist-spots/{id}', [TouristSpotController::class, 'show']);

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::get('/top-municipalities', [AnalyticsController::class, 'topMunicipalities']);
            Route::get('/top-spots', [AnalyticsController::class, 'topSpots']);
            Route::get('/chart-data', [AnalyticsController::class, 'chartData']);
            Route::get('/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
            Route::get('/filter-options', [AnalyticsController::class, 'filterOptions']);
            Route::get('/dashboard-data', [AnalyticsController::class, 'dashboard']);
            Route::get('/full', [AnalyticsController::class, 'full']);
            Route::get('/export', [AnalyticsController::class, 'export']);
        });

        // Fare Data (full access)
        Route::prefix('fare-data')->group(function () {
            Route::get('/stats', [FareDataController::class, 'stats']);
            Route::get('/guides', [FareDataController::class, 'guides']);
            Route::get('/matrices', [FareDataController::class, 'matrices']);
            Route::get('/uploads', [FareDataController::class, 'uploads']);
            Route::get('/import-logs', [FareDataController::class, 'importLogs']);
            Route::get('/validation-errors', [FareDataController::class, 'validationErrors']);
            Route::post('/upload', [FareDataController::class, 'upload']);
            Route::post('/sync', [FareDataController::class, 'sync']);
            Route::post('/', [FareDataController::class, 'store']);
            Route::put('/{id}', [FareDataController::class, 'update']);
            Route::delete('/{id}', [FareDataController::class, 'destroy']);
        });

        // User Management (full CRUD)
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/municipalities', [UserController::class, 'municipalities']);
            Route::get('/audit-logs', [UserController::class, 'auditLogs']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::post('/', [UserController::class, 'store']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::patch('/{id}/status', [UserController::class, 'toggleStatus']);
            Route::patch('/{id}/password', [UserController::class, 'resetPassword']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::patch('/{id}/archive', [UserController::class, 'archive']);
            Route::patch('/{id}/restore', [UserController::class, 'restore']);
        });

        // Archive Management
        Route::prefix('archive')->group(function () {
            Route::get('/stats', [ArchiveManagementController::class, 'stats']);
            Route::get('/fares', [ArchiveManagementController::class, 'archivedFares']);
            Route::get('/fares/{id}', [ArchiveManagementController::class, 'archivedFareDetail']);
            Route::post('/fares/{id}/restore', [ArchiveManagementController::class, 'restore']);
            Route::delete('/fares/{id}', [ArchiveManagementController::class, 'permanentDelete']);
        });


        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/stream', [ActivityLogController::class, 'stream']);
        Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats']);
        Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);

        // Notifications
        Route::get('/notifications/recent', [NotificationController::class, 'recent']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/stream', [NotificationController::class, 'stream']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll']);

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/profile', [SettingsController::class, 'profile']);
            Route::put('/profile', [SettingsController::class, 'updateProfile']);
            Route::put('/password', [SettingsController::class, 'updatePassword']);
            // Backup
            Route::get('/backup/list', [BackupController::class, 'list']);
            Route::get('/backup/stats', [BackupController::class, 'stats']);
            Route::post('/backup/create', [BackupController::class, 'create']);
            Route::post('/backup/restore', [BackupController::class, 'restoreFromFile']);
            Route::get('/backup/download/{filename}', [BackupController::class, 'download']);
            Route::delete('/backup/{filename}', [BackupController::class, 'delete']);
        });

        // Leaderboard
        Route::prefix('leaderboard')->group(function () {
            Route::get('/', [LeaderboardController::class, 'index']);
            Route::get('/top3', [LeaderboardController::class, 'top3']);
            Route::get('/kpis', [LeaderboardController::class, 'kpis']);
        });

        // Feedback Module
        Route::prefix('feedback')->group(function () {
            Route::get('/dashboard-stats', [FeedbackManagementController::class, 'dashboardStats']);
            Route::get('/gallery', [FeedbackManagementController::class, 'gallery']);
            Route::get('/table', [FeedbackManagementController::class, 'table']);
            Route::get('/spot-details/{id}', [FeedbackManagementController::class, 'spotDetails']);
        });

        // Proof Images Validation (read-only for PICTO)
        Route::prefix('proof-validation')->group(function () {
            Route::get('/stats', [ProofValidationController::class, 'stats']);
            Route::get('/', [ProofValidationController::class, 'index']);
            Route::get('/{id}', [ProofValidationController::class, 'show']);
        });

        // Voucher & Rewards (view only for PICTO)
        Route::prefix('vouchers')->group(function () {
            Route::get('/stats', [VoucherController::class, 'stats']);
            Route::get('/redemptions', [VoucherController::class, 'redemptions']);
            Route::get('/', [VoucherController::class, 'index']);
            Route::get('/{id}', [VoucherController::class, 'show']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  LUPTO (lupto role)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('lupto')->middleware('role:lupto')->group(function () {
        // Classification Points configuration
        Route::get('/classification-points', [ClassificationPointController::class, 'index']);
        Route::put('/classification-points', [ClassificationPointController::class, 'update']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/poll', [DashboardController::class, 'poll']);
        Route::get('/dashboard/pending-spots', [DashboardController::class, 'pendingSpots']);
        Route::post('/dashboard/approve-spot', [DashboardController::class, 'approveSpot']);
        Route::post('/dashboard/reject-spot', [DashboardController::class, 'rejectSpot']);
        Route::post('/dashboard/batch-approve-spots', [DashboardController::class, 'batchApproveSpots']);

        // Map - for LUPTO to see all municipalities
        Route::get('/map', [MapController::class, 'luptoMapData']);

        // Tourist Spots (alias to shared controller for map-view.php)
        Route::get('/tourist-spots', [TouristSpotController::class, 'index']);
        Route::get('/tourist-spots/{id}', [TouristSpotController::class, 'show']);

        // Analytics (read-only)
        Route::prefix('analytics')->group(function () {
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::get('/top-municipalities', [AnalyticsController::class, 'topMunicipalities']);
            Route::get('/top-spots', [AnalyticsController::class, 'topSpots']);
            Route::get('/chart-data', [AnalyticsController::class, 'chartData']);
            Route::get('/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
            Route::get('/filter-options', [AnalyticsController::class, 'filterOptions']);
            Route::get('/dashboard-data', [AnalyticsController::class, 'dashboard']);
            Route::get('/full', [AnalyticsController::class, 'full']);
            Route::get('/export', [AnalyticsController::class, 'export']);
        });

        // Fare Data (view-only)
        Route::prefix('fare-data')->group(function () {
            Route::get('/guides', [FareDataController::class, 'guides']);
            Route::get('/matrices', [FareDataController::class, 'matrices']);
            Route::get('/uploads', [FareDataController::class, 'uploads']);
            Route::get('/import-logs', [FareDataController::class, 'importLogs']);
            Route::get('/validation-errors', [FareDataController::class, 'validationErrors']);
        });

        // Leaderboard
        Route::prefix('leaderboard')->group(function () {
            Route::get('/', [LeaderboardController::class, 'index']);
            Route::get('/top3', [LeaderboardController::class, 'top3']);
            Route::get('/kpis', [LeaderboardController::class, 'kpis']);
        });

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/stream', [ActivityLogController::class, 'stream']);
        Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats']);

        // Notifications
        Route::get('/notifications/recent', [NotificationController::class, 'recent']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/stream', [NotificationController::class, 'stream']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll']);


        // User Management (full CRUD — can only add municipal users)
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/municipalities', [UserController::class, 'municipalities']);
            Route::get('/audit-logs', [UserController::class, 'auditLogs']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::post('/', [UserController::class, 'store']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::patch('/{id}/status', [UserController::class, 'toggleStatus']);
            Route::patch('/{id}/password', [UserController::class, 'resetPassword']);
            Route::patch('/{id}/archive', [UserController::class, 'archive']);
            Route::patch('/{id}/restore', [UserController::class, 'restore']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/profile', [SettingsController::class, 'profile']);
            Route::put('/profile', [SettingsController::class, 'updateProfile']);
            Route::put('/password', [SettingsController::class, 'updatePassword']);
            // Backup
            Route::get('/backup/list', [BackupController::class, 'list']);
            Route::get('/backup/stats', [BackupController::class, 'stats']);
            Route::post('/backup/create', [BackupController::class, 'create']);
            Route::post('/backup/restore', [BackupController::class, 'restoreFromFile']);
            Route::get('/backup/download/{filename}', [BackupController::class, 'download']);
            Route::delete('/backup/{filename}', [BackupController::class, 'delete']);
        });

        // Feedback Module
        Route::prefix('feedback')->group(function () {
            Route::get('/dashboard-stats', [FeedbackManagementController::class, 'dashboardStats']);
            Route::get('/gallery', [FeedbackManagementController::class, 'gallery']);
            Route::get('/table', [FeedbackManagementController::class, 'table']);
            Route::get('/spot-details/{id}', [FeedbackManagementController::class, 'spotDetails']);
        });

        // Proof Images Validation (read-only for LUPTO)
        Route::prefix('proof-validation')->group(function () {
            Route::get('/stats', [ProofValidationController::class, 'stats']);
            Route::get('/', [ProofValidationController::class, 'index']);
            Route::get('/{id}', [ProofValidationController::class, 'show']);
        });

        // Voucher & Rewards (Full Access for LUPTO)
        Route::prefix('vouchers')->group(function () {
            Route::get('/stats', [VoucherController::class, 'stats']);
            Route::get('/redemptions', [VoucherController::class, 'redemptions']);
            Route::patch('/redemptions/{id}/status', [VoucherController::class, 'updateRedemptionStatus']);
            Route::get('/', [VoucherController::class, 'index']);
            Route::get('/{id}', [VoucherController::class, 'show']);
            Route::post('/', [VoucherController::class, 'store']);
            Route::put('/{id}', [VoucherController::class, 'update']);
            Route::patch('/{id}/status', [VoucherController::class, 'toggleStatus']);
            Route::patch('/{id}/archive', [VoucherController::class, 'archive']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  MUNICIPAL (all *_mto + 'municipal' roles)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('municipal')->middleware('role:municipal')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/poll', [DashboardController::class, 'poll']);

        // Analytics (scoped to own municipality)
        Route::prefix('analytics')->group(function () {
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::get('/top-municipalities', [AnalyticsController::class, 'topMunicipalities']);
            Route::get('/top-spots', [AnalyticsController::class, 'topSpots']);
            Route::get('/chart-data', [AnalyticsController::class, 'chartData']);
            Route::get('/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
            Route::get('/filter-options', [AnalyticsController::class, 'filterOptions']);
            Route::get('/dashboard-data', [AnalyticsController::class, 'dashboard']);
            Route::get('/full', [AnalyticsController::class, 'full']);
            Route::get('/export', [AnalyticsController::class, 'export']);
        });

        // Fare Data (upload + view)
        Route::prefix('fare-data')->group(function () {
            Route::get('/guides', [FareDataController::class, 'guides']);
            Route::get('/matrices', [FareDataController::class, 'matrices']);
            Route::get('/uploads', [FareDataController::class, 'uploads']);
            Route::get('/import-logs', [FareDataController::class, 'importLogs']);
            Route::get('/validation-errors', [FareDataController::class, 'validationErrors']);
            Route::post('/upload', [FareDataController::class, 'upload']);
            Route::post('/sync', [FareDataController::class, 'sync']);
            Route::post('/', [FareDataController::class, 'store']);
        });

        // Tourist Spots (CRUD scoped to own municipality)
        Route::prefix('tourist-spots')->group(function () {
            Route::get('/', [TouristSpotController::class, 'index']);
            Route::get('/{id}', [TouristSpotController::class, 'show']);
            Route::post('/upload-image', [TouristSpotController::class, 'uploadImage']);
            Route::post('/', [TouristSpotController::class, 'store']);
            Route::put('/{id}', [TouristSpotController::class, 'update']);
            Route::delete('/{id}', [TouristSpotController::class, 'destroy']);
        });

        // Map
        Route::get('/map', [MapController::class, 'municipalityData']);

        // User Management (view + update)
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::patch('/{id}/password', [UserController::class, 'resetPassword']);
        });

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/stream', [ActivityLogController::class, 'stream']);
        Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats']);

        // Notifications
        Route::get('/notifications/recent', [NotificationController::class, 'recent']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/stream', [NotificationController::class, 'stream']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll']);


        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/profile', [SettingsController::class, 'profile']);
            Route::put('/profile', [SettingsController::class, 'updateProfile']);
            Route::put('/password', [SettingsController::class, 'updatePassword']);
            // Backup (municipal-scoped)
            Route::get('/backup/list', [BackupController::class, 'list']);
            Route::get('/backup/stats', [BackupController::class, 'stats']);
            Route::post('/backup/create', [BackupController::class, 'create']);
            Route::post('/backup/restore', [BackupController::class, 'restoreFromFile']);
            Route::get('/backup/download/{filename}', [BackupController::class, 'download']);
            Route::delete('/backup/{filename}', [BackupController::class, 'delete']);
        });

        // Feedback Module
        Route::prefix('feedback')->group(function () {
            Route::get('/dashboard-stats', [FeedbackManagementController::class, 'dashboardStats']);
            Route::get('/gallery', [FeedbackManagementController::class, 'gallery']);
            Route::get('/table', [FeedbackManagementController::class, 'table']);
            Route::get('/spot-details/{id}', [FeedbackManagementController::class, 'spotDetails']);
        });

        // Proof Images Validation — MTO has full access (municipality-scoped)
        Route::prefix('proof-validation')->group(function () {
            Route::get('/stats', [ProofValidationController::class, 'stats']);
            Route::get('/', [ProofValidationController::class, 'index']);
            Route::get('/{id}', [ProofValidationController::class, 'show']);
            Route::post('/{id}/approve', [ProofValidationController::class, 'approve']);
            Route::post('/{id}/reject', [ProofValidationController::class, 'reject']);
        });

        // Voucher & Rewards (view only for Municipal)
        Route::prefix('vouchers')->group(function () {
            Route::get('/stats', [VoucherController::class, 'stats']);
            Route::get('/redemptions', [VoucherController::class, 'redemptions']);
            Route::get('/', [VoucherController::class, 'index']);
            Route::get('/{id}', [VoucherController::class, 'show']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  TOURIST (mobile / public tourist endpoints)
    //  Fix #5: Requires valid Bearer token via 'tourist.auth' middleware
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('tourist')->middleware('tourist.auth')->group(function () {
        Route::get('/feedback', [FeedbackController::class, 'index']);
        Route::post('/feedback', [FeedbackController::class, 'store']);

        // Voucher & Rewards (Mobile API endpoints)
        Route::prefix('vouchers')->group(function () {
            Route::get('/', [VoucherController::class, 'index']);
            Route::get('/{id}', [VoucherController::class, 'show']);
            Route::post('/{id}/redeem', [VoucherController::class, 'redeem']);
        });
    });
});


