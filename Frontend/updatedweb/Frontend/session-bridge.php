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

