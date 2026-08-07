<?php
/**
 * sync-session.php
 *
 * Syncs the Laravel API login result into the PHP frontend session.
 *
 * Security: uses the PHP session's own CSRF token (set by login.php) to
 * verify the request is legitimate. This works correctly because login.php
 * and sync-session.php share the same PHP session (same server/port).
 *
 * NOTE: The previous _pending_sync_token approach was removed because it
 * stored the token in the Laravel session (port 8000) which is a completely
 * separate PHP session from the frontend (port 8080/XAMPP). They cannot share
 * session data. The CSRF token approach is correct and reliable.
 */
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// ── Session flag clear operations (safe — no user data written) ────────────
if (isset($input['clear_just_logged_in'])) {
    unset($_SESSION['just_logged_in']);
    echo json_encode(['success' => true]);
    exit;
}
if (isset($input['clear_must_change_password'])) {
    unset($_SESSION['must_change_password']);
    unset($_SESSION['just_logged_in']);
    echo json_encode(['success' => true]);
    exit;
}

// ── Session sync: verify using the PHP session's own CSRF token ────────────
if (isset($input['user'])) {
    $providedCsrf = $input['_csrf'] ?? '';
    $storedCsrf   = $_SESSION['csrf_token'] ?? '';

    // Reject if CSRF token is missing or doesn't match what login.php put in the session
    if (
        empty($providedCsrf) ||
        empty($storedCsrf) ||
        !hash_equals($storedCsrf, $providedCsrf)
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    // Write session data
    $_SESSION['user_id']                = $input['user']['id'];
    $_SESSION['user_name']              = $input['user']['name'];
    $_SESSION['user_email']             = $input['user']['email'];
    $_SESSION['user_role']              = $input['user']['role'];
    $_SESSION['user_municipality_id']   = $input['user']['municipality_id'] ?? null;
    $_SESSION['user_municipality_name'] = $input['user']['municipality_name'] ?? null;
    $_SESSION['must_change_password']   = $input['user']['must_change_password'] ?? false;

    if ($_SESSION['must_change_password']) {
        $_SESSION['just_logged_in'] = true;
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'No action specified.']);
