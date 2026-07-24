<?php

/**
 * Send Welcome Email Endpoint
 *
 * POST /api/send_welcome_email.php
 * Body: { "email": "...", "name": "...", "password": "..." }
 * Called after LUPTO creates a new user account.
 */

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email']    ?? '');
$name     = trim($input['name']     ?? 'User');
$password = trim($input['password'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid email is required.']);
    exit;
}

$loginUrl = dirname(dirname($_SERVER['SCRIPT_NAME']));

$html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Outfit',Arial,sans-serif;background:#F1F5F9;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:40px auto;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
        <tr>
            <td style="background:linear-gradient(135deg,#06444D,#0D6557);padding:32px 32px 24px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#FFFFFF;letter-spacing:1px;">INTAN ELYU</div>
                <div style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:4px;">Tourist Spots Management System</div>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 32px 24px;">
                <h2 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#1E293B;">Welcome, {$name}!</h2>
                <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#4B5563;">
                    Your INTAN ELYU account has been created. You can now sign in to the Tourist Spots Management System using the credentials below.
                </p>
                <div style="background:#F8FAFC;border:1px solid #E5E7EB;border-radius:10px;padding:16px 20px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #E5E7EB;">
                        <span style="font-size:13px;color:#6B7280;font-weight:500;">Email</span>
                        <span style="font-size:13px;color:#1E293B;font-weight:600;">{$email}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;">
                        <span style="font-size:13px;color:#6B7280;font-weight:500;">Password</span>
                        <span style="font-size:13px;color:#1E293B;font-weight:600;">{$password}</span>
                    </div>
                </div>
                <p style="margin:0 0 20px;font-size:12px;color:#F59E0B;font-weight:500;">
                    <i class="fas fa-exclamation-triangle"></i> For security, please change your password after your first login.
                </p>
                <div style="text-align:center;">
                    <a href="{$loginUrl}/login.php"
                       style="display:inline-block;background:linear-gradient(135deg,#06444D,#0D6557);color:#FFFFFF;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:15px;font-weight:600;">
                        Sign In to INTAN ELYU
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 32px 24px;border-top:1px solid #E5E7EB;">
                <p style="margin:0;font-size:12px;color:#9CA3AF;">
                    &copy; 2026 City Tourism Office &bull; San Fernando City, La Union
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

$result = EmailService::send([
    'to'       => $email,
    'subject'  => 'Welcome to INTAN ELYU - Your Account is Ready',
    'html'     => $html,
    'altBody'  => "Welcome, $name!\n\nYour INTAN ELYU account has been created.\n\nEmail: $email\nPassword: $password\n\nSign in at: $loginUrl/login.php\n\nPlease change your password after your first login.",
    'fromName' => 'INTAN ELYU Tourism',
]);

echo json_encode([
    'success'      => $result['success'],
    'message'      => $result['success'] ? 'Welcome email sent.' : 'Email delivery failed.',
    'sender_email' => $result['sender_email'],
    'error'        => $result['error'],
]);
