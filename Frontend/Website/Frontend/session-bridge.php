<?php
session_start();

// Check if PHP session has user data
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('is_ajax_request')) {
    function is_ajax_request() {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || isset($_GET['spa_ajax']) 
            || (isset($_SERVER['HTTP_X_SPA_REQUEST']) && $_SERVER['HTTP_X_SPA_REQUEST'] === 'true');
    }
}

// Force password change on first login (blocks API calls)
if (!empty($_SESSION['must_change_password'])) {
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    if ($scriptName !== 'settings.php' && $scriptName !== 'logout.php' && $scriptName !== 'sync-session.php') {
        if (is_ajax_request()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'First-time login password change required.',
                'must_change_password' => true,
                'redirect' => 'settings.php'
            ]);
            exit;
        }
    }
}

