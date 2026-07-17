<?php

/**
 * Email Sender Accounts CRUD API
 *
 * GET    /api/email_senders.php              — list all senders
 * POST   /api/email_senders.php              — add a sender
 * PUT    /api/email_senders.php?id=<id>      — update a sender
 * DELETE /api/email_senders.php?id=<id>      — delete a sender
 * POST   /api/email_senders.php?action=test — test credentials
 * POST   /api/email_senders.php?action=toggle — enable/disable a sender
 */

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Auth check — restrict to LUPTO/PICTO admins
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['lupto', 'picto'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

try {
    $db = getDb();

    if ($method === 'GET') {
        $stmt = $db->query(
            'SELECT id, email, name, priority, is_active, is_default, emails_sent, created_at, updated_at
             FROM email_sender_accounts
             ORDER BY priority ASC, id ASC'
        );
        $senders = $stmt->fetchAll();

        foreach ($senders as &$s) {
            $s['id']          = (int) $s['id'];
            $s['priority']    = (int) $s['priority'];
            $s['is_active']   = (bool) $s['is_active'];
            $s['is_default']  = (bool) $s['is_default'];
            $s['emails_sent'] = (int) $s['emails_sent'];
            $s['has_password'] = true;  // Password exists but not exposed
        }
        unset($s);

        echo json_encode(['success' => true, 'senders' => $senders]);
        exit;
    }

    if ($method === 'POST') {
        $input  = json_decode(file_get_contents('php://input'), true);
        $action = $_GET['action'] ?? '';

        if ($action === 'test') {
            // Validate credentials without saving
            $email    = trim($input['email']    ?? '');
            $password = trim($input['app_password'] ?? '');

            if (empty($email) || empty($password)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Email and App Password are required.']);
                exit;
            }

            $result = EmailService::validateCredentials($email, $password);
            echo json_encode([
                'success' => $result['valid'],
                'message' => $result['valid'] ? 'Credentials verified successfully.' : ($result['error'] ?: 'Validation failed.'),
            ]);
            exit;
        }

        if ($action === 'toggle') {
            $id   = (int) ($input['id'] ?? 0);
            $stmt = $db->prepare('UPDATE email_sender_accounts SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Sender status toggled.']);
            exit;
        }

        // Add new sender
        $email    = trim($input['email']    ?? '');
        $name     = trim($input['name']     ?? '');
        $password = trim($input['app_password'] ?? '');
        $priority = (int) ($input['priority']  ?? 10);
        $isDefault = !empty($input['is_default']);

        if (empty($email) || empty($password)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Email and App Password are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Check duplicate
        $stmt = $db->prepare('SELECT id FROM email_sender_accounts WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A sender with this email already exists.']);
            exit;
        }

        $encrypted = EmailService::encrypt($password);

        if ($isDefault) {
            $db->exec('UPDATE email_sender_accounts SET is_default = 0');
        }

        $stmt = $db->prepare(
            'INSERT INTO email_sender_accounts (email, name, app_password, priority, is_active, is_default, created_at)
             VALUES (:email, :name, :app_password, :priority, 1, :is_default, NOW())'
        );
        $stmt->execute([
            ':email'        => $email,
            ':name'         => $name,
            ':app_password' => $encrypted,
            ':priority'     => $priority,
            ':is_default'   => $isDefault ? 1 : 0,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Sender account added.',
            'id'      => (int) $db->lastInsertId(),
        ]);
        exit;
    }

    if ($method === 'PUT') {
        $id    = (int) ($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing sender ID.']);
            exit;
        }

        $email    = trim($input['email']    ?? '');
        $name     = trim($input['name']     ?? '');
        $password = trim($input['app_password'] ?? '');
        $priority = (int) ($input['priority']  ?? 10);
        $isDefault = !empty($input['is_default']);

        if (empty($email)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Email is required.']);
            exit;
        }

        $fields = 'email = :email, name = :name, priority = :priority, is_default = :is_default, updated_at = NOW()';
        $params = [
            ':id'         => $id,
            ':email'      => $email,
            ':name'       => $name,
            ':priority'   => $priority,
            ':is_default' => $isDefault ? 1 : 0,
        ];

        if (!empty($password)) {
            $fields .= ', app_password = :app_password';
            $params[':app_password'] = EmailService::encrypt($password);
        }

        if ($isDefault) {
            $db->exec('UPDATE email_sender_accounts SET is_default = 0');
        }

        $stmt = $db->prepare("UPDATE email_sender_accounts SET $fields WHERE id = :id");
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Sender account updated.']);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing sender ID.']);
            exit;
        }

        $stmt = $db->prepare('DELETE FROM email_sender_accounts WHERE id = :id');
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Sender account removed.']);
        exit;
    }

} catch (Exception $e) {
    error_log('Email senders API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
