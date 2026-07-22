<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FareDataController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Pitco\ArchiveManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportGeneratorController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TouristSpotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Admin\FeedbackManagementController;
use App\Http\Controllers\FeedbackController;

use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
//  Auth (public)
// ─────────────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login',    [LoginController::class,   'login']);
    Route::post('/logout',   [LogoutController::class,  'logout']);
    Route::post('/register', [RegisterController::class,'register']);
    Route::get('/check',     [SessionController::class, 'check']);
});

// ─────────────────────────────────────────────────────────────────────────────
//  Public image serving (no auth required — served to <img> tags in HTML)
// ─────────────────────────────────────────────────────────────────────────────
Route::get('/images/tourist-spots/{filename}', [TouristSpotController::class, 'serveImage']);
Route::get('/serve-image',                      [TouristSpotController::class, 'serveImageProxy']);
Route::get('/serve-image.php',                  [TouristSpotController::class, 'serveImageProxy']);

// ─────────────────────────────────────────────────────────────────────────────
//  Authenticated routes
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('auth.session')->group(function () {

    // Profile (any authenticated role)
    Route::prefix('profile')->group(function () {
        Route::get('/',          [ProfileController::class, 'show']);
        Route::put('/',          [ProfileController::class, 'update']);
        Route::put('/password',  [ProfileController::class, 'updatePassword']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  SHARED TOURIST SPOTS (all roles - PICTO: read-only; LUPTO/MUNICIPAL: full CRUD)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('tourist-spots')->group(function () {
        Route::get('/',               [TouristSpotController::class, 'index']);
        Route::get('/{id}',           [TouristSpotController::class, 'show']);
        Route::post('/upload-image',  [TouristSpotController::class, 'uploadImage']);
        Route::post('/',              [TouristSpotController::class, 'store']);
        Route::put('/{id}',           [TouristSpotController::class, 'update']);
        Route::delete('/{id}',        [TouristSpotController::class, 'destroy']);
    });

    // Municipalities (shared read)
    Route::get('/municipalities',      [MunicipalityController::class, 'index']);
    Route::get('/municipalities/{id}', [MunicipalityController::class, 'show']);

    // ─────────────────────────────────────────────────────────────────────────
    //  PITCO (picto role)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('pitco')->middleware('role:picto')->group(function () {
        // Dashboard
        Route::get('/dashboard',                    [DashboardController::class, 'index']);
        Route::get('/dashboard/pending-spots',      [DashboardController::class, 'pendingSpots']);
        Route::post('/dashboard/approve-spot',      [DashboardController::class, 'approveSpot']);
        Route::post('/dashboard/reject-spot',       [DashboardController::class, 'rejectSpot']);
        Route::get('/map',                          [MapController::class, 'luptoMapData']);

        // Tourist Spots (full CRUD)
        Route::get('/tourist-spots',         [TouristSpotController::class, 'index']);
        Route::get('/tourist-spots/{id}',    [TouristSpotController::class, 'show']);
        Route::post('/tourist-spots',        [TouristSpotController::class, 'store']);
        Route::put('/tourist-spots/{id}',    [TouristSpotController::class, 'update']);
        Route::delete('/tourist-spots/{id}', [TouristSpotController::class, 'destroy']);
        Route::post('/tourist-spots/upload-image', [TouristSpotController::class, 'uploadImage']);

        // Analytics
            Route::prefix('analytics')->group(function () {
                Route::get('/summary',              [AnalyticsController::class, 'summary']);
                Route::get('/top-municipalities',   [AnalyticsController::class, 'topMunicipalities']);
                Route::get('/top-spots',            [AnalyticsController::class, 'topSpots']);
                Route::get('/chart-data',           [AnalyticsController::class, 'chartData']);
                Route::get('/monthly-trend',        [AnalyticsController::class, 'monthlyTrend']);
                Route::get('/filter-options',       [AnalyticsController::class, 'filterOptions']);
                Route::get('/full',                 [AnalyticsController::class, 'full']);
                Route::get('/export',               [AnalyticsController::class, 'export']);
            });

        // Fare Data (full access)
        Route::prefix('fare-data')->group(function () {
            Route::get('/stats',              [FareDataController::class, 'stats']);
            Route::get('/guides',             [FareDataController::class, 'guides']);
            Route::get('/matrices',           [FareDataController::class, 'matrices']);
            Route::get('/uploads',            [FareDataController::class, 'uploads']);
            Route::get('/import-logs',        [FareDataController::class, 'importLogs']);
            Route::get('/validation-errors',  [FareDataController::class, 'validationErrors']);
            Route::post('/upload',            [FareDataController::class, 'upload']);
            Route::post('/sync',              [FareDataController::class, 'sync']);
            Route::post('/',                  [FareDataController::class, 'store']);
            Route::put('/{id}',               [FareDataController::class, 'update']);
            Route::delete('/{id}',            [FareDataController::class, 'destroy']);
        });

        // User Management (full CRUD)
        Route::prefix('users')->group(function () {
            Route::get('/',                  [UserController::class, 'index']);
            Route::get('/municipalities',    [UserController::class, 'municipalities']);
            Route::get('/audit-logs',        [UserController::class, 'auditLogs']);
            Route::get('/{id}',              [UserController::class, 'show']);
            Route::post('/',                 [UserController::class, 'store']);
            Route::put('/{id}',              [UserController::class, 'update']);
            Route::patch('/{id}/status',     [UserController::class, 'toggleStatus']);
            Route::patch('/{id}/password',   [UserController::class, 'resetPassword']);
            Route::delete('/{id}',           [UserController::class, 'destroy']);
            Route::patch('/{id}/archive',    [UserController::class, 'archive']);
            Route::patch('/{id}/restore',    [UserController::class, 'restore']);
        });

        // Archive Management
        Route::prefix('archive')->group(function () {
            Route::get('/stats',            [ArchiveManagementController::class, 'stats']);
            Route::get('/fares',            [ArchiveManagementController::class, 'archivedFares']);
            Route::get('/fares/{id}',       [ArchiveManagementController::class, 'archivedFareDetail']);
            Route::post('/fares/{id}/restore',  [ArchiveManagementController::class, 'restore']);
            Route::delete('/fares/{id}',    [ArchiveManagementController::class, 'permanentDelete']);
        });

        // Reports
        Route::get('/reports', [ReportGeneratorController::class, 'index']);

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
            Route::get('/profile',          [SettingsController::class, 'profile']);
            Route::put('/profile',          [SettingsController::class, 'updateProfile']);
            Route::put('/password',         [SettingsController::class, 'updatePassword']);
        });

        // Leaderboard
        Route::prefix('leaderboard')->group(function () {
            Route::get('/',       [LeaderboardController::class, 'index']);
            Route::get('/top3',   [LeaderboardController::class, 'top3']);
            Route::get('/kpis',   [LeaderboardController::class, 'kpis']);
        });

        // Feedback Module
        Route::prefix('feedback')->group(function () {
            Route::get('/dashboard-stats',   [FeedbackManagementController::class, 'dashboardStats']);
            Route::get('/gallery',           [FeedbackManagementController::class, 'gallery']);
            Route::get('/table',             [FeedbackManagementController::class, 'table']);
            Route::get('/spot-details/{id}', [FeedbackManagementController::class, 'spotDetails']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  LUPTO (lupto role)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('lupto')->middleware('role:lupto')->group(function () {
        // Dashboard
        Route::get('/dashboard',                    [DashboardController::class, 'index']);
        Route::get('/dashboard/poll',               [DashboardController::class, 'poll']);
        Route::get('/dashboard/pending-spots',      [DashboardController::class, 'pendingSpots']);
        Route::post('/dashboard/approve-spot',      [DashboardController::class, 'approveSpot']);
        Route::post('/dashboard/reject-spot',       [DashboardController::class, 'rejectSpot']);
        Route::post('/dashboard/batch-approve-spots',[DashboardController::class,'batchApproveSpots']);

        // Map - for LUPTO to see all municipalities
        Route::get('/map', [MapController::class, 'luptoMapData']);

        // Tourist Spots (alias to shared controller for map-view.php)
        Route::get('/tourist-spots',     [TouristSpotController::class, 'index']);
        Route::get('/tourist-spots/{id}',[TouristSpotController::class, 'show']);

        // Analytics (read-only)
        Route::prefix('analytics')->group(function () {
            Route::get('/summary',              [AnalyticsController::class, 'summary']);
            Route::get('/top-municipalities',   [AnalyticsController::class, 'topMunicipalities']);
            Route::get('/top-spots',            [AnalyticsController::class, 'topSpots']);
            Route::get('/chart-data',           [AnalyticsController::class, 'chartData']);
            Route::get('/monthly-trend',        [AnalyticsController::class, 'monthlyTrend']);
            Route::get('/filter-options',       [AnalyticsController::class, 'filterOptions']);
            Route::get('/full',                 [AnalyticsController::class, 'full']);
            Route::get('/export',               [AnalyticsController::class, 'export']);
        });

        // Fare Data (view-only)
        Route::prefix('fare-data')->group(function () {
            Route::get('/guides',             [FareDataController::class, 'guides']);
            Route::get('/matrices',           [FareDataController::class, 'matrices']);
            Route::get('/uploads',            [FareDataController::class, 'uploads']);
            Route::get('/import-logs',        [FareDataController::class, 'importLogs']);
            Route::get('/validation-errors',  [FareDataController::class, 'validationErrors']);
        });

        // Leaderboard
        Route::prefix('leaderboard')->group(function () {
            Route::get('/',       [LeaderboardController::class, 'index']);
            Route::get('/top3',   [LeaderboardController::class, 'top3']);
            Route::get('/kpis',   [LeaderboardController::class, 'kpis']);
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

        // Reports
        Route::get('/reports', [ReportGeneratorController::class, 'index']);

        // User Management (full CRUD — can only add municipal users)
        Route::prefix('users')->group(function () {
            Route::get('/',                  [UserController::class, 'index']);
            Route::get('/municipalities',    [UserController::class, 'municipalities']);
            Route::get('/audit-logs',        [UserController::class, 'auditLogs']);
            Route::get('/{id}',              [UserController::class, 'show']);
            Route::post('/',                 [UserController::class, 'store']);
            Route::put('/{id}',              [UserController::class, 'update']);
            Route::patch('/{id}/status',     [UserController::class, 'toggleStatus']);
            Route::patch('/{id}/password',   [UserController::class, 'resetPassword']);
            Route::patch('/{id}/archive',    [UserController::class, 'archive']);
            Route::patch('/{id}/restore',    [UserController::class, 'restore']);
            Route::delete('/{id}',           [UserController::class, 'destroy']);
        });

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/profile',  [SettingsController::class, 'profile']);
            Route::put('/profile',  [SettingsController::class, 'updateProfile']);
            Route::put('/password', [SettingsController::class, 'updatePassword']);
        });

        // Feedback Module
        Route::prefix('feedback')->group(function () {
            Route::get('/dashboard-stats',   [FeedbackManagementController::class, 'dashboardStats']);
            Route::get('/gallery',           [FeedbackManagementController::class, 'gallery']);
            Route::get('/table',             [FeedbackManagementController::class, 'table']);
            Route::get('/spot-details/{id}', [FeedbackManagementController::class, 'spotDetails']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  MUNICIPAL (all *_mto + 'municipal' roles)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('municipal')->middleware('role:municipal')->group(function () {
        // Dashboard
        Route::get('/dashboard',      [DashboardController::class, 'index']);
        Route::get('/dashboard/poll', [DashboardController::class, 'poll']);

        // Analytics (scoped to own municipality)
        Route::prefix('analytics')->group(function () {
            Route::get('/summary',              [AnalyticsController::class, 'summary']);
            Route::get('/top-municipalities',   [AnalyticsController::class, 'topMunicipalities']);
            Route::get('/top-spots',            [AnalyticsController::class, 'topSpots']);
            Route::get('/chart-data',           [AnalyticsController::class, 'chartData']);
            Route::get('/monthly-trend',        [AnalyticsController::class, 'monthlyTrend']);
            Route::get('/filter-options',       [AnalyticsController::class, 'filterOptions']);
            Route::get('/full',                 [AnalyticsController::class, 'full']);
            Route::get('/export',               [AnalyticsController::class, 'export']);
        });

        // Fare Data (upload + view)
        Route::prefix('fare-data')->group(function () {
            Route::get('/guides',             [FareDataController::class, 'guides']);
            Route::get('/matrices',           [FareDataController::class, 'matrices']);
            Route::get('/uploads',            [FareDataController::class, 'uploads']);
            Route::get('/import-logs',        [FareDataController::class, 'importLogs']);
            Route::get('/validation-errors',  [FareDataController::class, 'validationErrors']);
            Route::post('/upload',            [FareDataController::class, 'upload']);
            Route::post('/sync',              [FareDataController::class, 'sync']);
            Route::post('/',                  [FareDataController::class, 'store']);
        });

        // Tourist Spots (CRUD scoped to own municipality)
        Route::prefix('tourist-spots')->group(function () {
            Route::get('/',               [TouristSpotController::class, 'index']);
            Route::get('/{id}',           [TouristSpotController::class, 'show']);
            Route::post('/upload-image',  [TouristSpotController::class, 'uploadImage']);
            Route::post('/',              [TouristSpotController::class, 'store']);
            Route::put('/{id}',           [TouristSpotController::class, 'update']);
            Route::delete('/{id}',        [TouristSpotController::class, 'destroy']);
        });

        // Map
        Route::get('/map', [MapController::class, 'municipalityData']);

        // User Management (view + update)
        Route::prefix('users')->group(function () {
            Route::get('/',               [UserController::class, 'index']);
            Route::put('/{id}',           [UserController::class, 'update']);
            Route::patch('/{id}/password',[UserController::class, 'resetPassword']);
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

        // Reports
        Route::get('/reports', [ReportGeneratorController::class, 'index']);

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/profile',  [SettingsController::class, 'profile']);
            Route::put('/profile',  [SettingsController::class, 'updateProfile']);
            Route::put('/password', [SettingsController::class, 'updatePassword']);
        });

        // Feedback Module
        Route::prefix('feedback')->group(function () {
            Route::get('/dashboard-stats',   [FeedbackManagementController::class, 'dashboardStats']);
            Route::get('/gallery',           [FeedbackManagementController::class, 'gallery']);
            Route::get('/table',             [FeedbackManagementController::class, 'table']);
            Route::get('/spot-details/{id}', [FeedbackManagementController::class, 'spotDetails']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    //  TOURIST (mobile / public tourist endpoints)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('tourist')->group(function () {
        Route::get('/feedback',  [FeedbackController::class, 'index']);
        Route::post('/feedback', [FeedbackController::class, 'store']);
    });
});

