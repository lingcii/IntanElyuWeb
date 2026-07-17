<?php
/**
 * Re-establish Laravel API session using credentials (for users logged in before session bridge existed).
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

require_once __DIR__ . '/laravel-api-bridge.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? $_SESSION['user_email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit;
}

$ok = establishLaravelSession($email, $password);
if (!$ok) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Could not connect to API. Check Laravel is running on port 8000.']);
    exit;
}

echo json_encode(['success' => true]);
