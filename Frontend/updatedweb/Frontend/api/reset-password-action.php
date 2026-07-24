<?php

/**
 * Reset Password Action Endpoint
 *
 * POST /api/reset-password-action.php
 * Accepts JSON body: { "token": "...", "password": "...", "password_confirmation": "..." }
 * Requires CSRF token via X-CSRF-TOKEN header.
 */

session_start();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$token              = trim($input['token'] ?? '');
$password           = $input['password'] ?? '';
$passwordConfirm    = $input['password_confirmation'] ?? '';

// CSRF check
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

// Validate token format
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid reset token.']);
    exit;
}

// Validate password strength
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}
if (!preg_match('/[A-Z]/', $password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
    exit;
}
if (!preg_match('/[a-z]/', $password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter.']);
    exit;
}
if (!preg_match('/[0-9]/', $password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
    exit;
}

if ($password !== $passwordConfirm) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

try {
    $db = getDb();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare(
        'SELECT id, email FROM frontend_password_resets
         WHERE token_hash = :hash AND expires_at > NOW() AND used = 0
         LIMIT 1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $resetRow = $stmt->fetch();

    if (!$resetRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This reset link is invalid or has expired.']);
        exit;
    }

    $email = $resetRow['email'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE email = :email');
    $stmt->execute([
        ':password' => $hashedPassword,
        ':email'    => $email,
    ]);

    $stmt = $db->prepare('UPDATE frontend_password_resets SET used = 1 WHERE id = :id');
    $stmt->execute([':id' => $resetRow['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully.',
    ]);
} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}
