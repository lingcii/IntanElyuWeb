<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'pitco') {
    $_SESSION['user_role'] = 'picto';
}

// Check if PHP session has user data
if (!isset($_SESSION['user_id'])) {
    $loginRedirect = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/views/') ? '../../login.php' : 'login.php';
    header('Location: ' . $loginRedirect);
    exit;
}

if (!function_exists('is_ajax_request')) {
    function is_ajax_request() {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || isset($_GET['spa_ajax']) 
            || (isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true');
    }
}

// Force password change on first login — blocks ALL navigation.
// Only settings.php and logout.php are allowed through.
if (!empty($_SESSION['must_change_password'])) {
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    $allowedScripts = ['settings.php', 'logout.php', 'sync-session.php'];

    if (!in_array($scriptName, $allowedScripts)) {
        if (is_ajax_request()) {
            // Block AJAX/SPA calls with a structured JSON error
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error'                => 'First-time login password change required.',
                'must_change_password' => true,
                'redirect'             => 'settings.php?first_login=1'
            ]);
            exit;
        } else {
            // Block full page loads — hard redirect to settings
            $settingsRedirect = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/views/')
                ? 'settings.php?first_login=1'
                : 'views/settings.php?first_login=1';
            header('Location: ' . $settingsRedirect);
            exit;
        }
    }
}

// ── Maintenance Mode enforcement (server-side) ──────────────────────────────
// PICTO is always allowed through. LUPTO and Municipal users are blocked
// while maintenance mode is active.
if (!function_exists('_sb_is_picto')) {
    function _sb_is_picto(string $role): bool {
        return in_array($role, ['picto', 'pitco'], true);
    }
}

$_sb_role = $_SESSION['user_role'] ?? '';
$_sb_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_sb_maintenance_exempt = ['logout.php', 'sync-session.php', 'maintenance.php'];

if (!_sb_is_picto($_sb_role) && !in_array($_sb_script, $_sb_maintenance_exempt)) {
    // Use session-cached maintenance status (TTL 5s) to avoid excessive API hits while responding fast
    $now = time();
    $cachedAt = $_SESSION['_maintenance_checked_at'] ?? 0;
    $cacheExpiry = 5; // seconds (fast real-time check)

    if (($now - $cachedAt) > $cacheExpiry) {
        // Fetch from Laravel API
        $_sb_api_base = 'http://127.0.0.1:8000';
        $_sb_ctx = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
            ]
        ]);
        $_sb_resp = @file_get_contents($_sb_api_base . '/api/system/maintenance-status', false, $_sb_ctx);
        if ($_sb_resp !== false) {
            $_sb_data = json_decode($_sb_resp, true);
            $_SESSION['_maintenance_active'] = !empty($_sb_data['maintenance']);
        }
        $_SESSION['_maintenance_checked_at'] = $now;
    }

    if (!empty($_SESSION['_maintenance_active'])) {
        if (is_ajax_request()) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode([
                'error'       => 'System under maintenance.',
                'maintenance' => true,
                'message'     => 'The system is currently under maintenance. Please try again later.',
            ]);
            exit;
        } else {
            // Redirect to maintenance screen
            $maintenanceRedirect = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/views/')
                ? 'maintenance.php'
                : 'views/maintenance.php';
            header('Location: ' . $maintenanceRedirect);
            exit;
        }
    }
}

