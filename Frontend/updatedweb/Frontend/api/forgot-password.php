<?php

/**
 * Forgot Password AJAX Endpoint
 *
 * POST /api/forgot-password.php
 * Uses the centralized EmailService for multi-sender delivery.
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
$email = trim($input['email'] ?? '');

// CSRF check
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Rate limiting — 5 requests per hour per IP
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$clientIp = trim(explode(',', $clientIp)[0]);

try {
    $db = getDb();

    $stmt = $db->prepare(
        'SELECT request_count, last_request_at FROM password_reset_rate_limits WHERE ip_address = :ip'
    );
    $stmt->execute([':ip' => $clientIp]);
    $limit = $stmt->fetch();

    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

    if ($limit) {
        if ((int) $limit['request_count'] >= 20 && $limit['last_request_at'] > $oneHourAgo) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many reset requests. Please try again in an hour.',
            ]);
            exit;
        }

        if ($limit['last_request_at'] <= $oneHourAgo) {
            $stmt = $db->prepare(
                'UPDATE password_reset_rate_limits SET request_count = 1, last_request_at = NOW() WHERE ip_address = :ip'
            );
            $stmt->execute([':ip' => $clientIp]);
        } else {
            $stmt = $db->prepare(
                'UPDATE password_reset_rate_limits SET request_count = request_count + 1, last_request_at = NOW() WHERE ip_address = :ip'
            );
            $stmt->execute([':ip' => $clientIp]);
        }
    } else {
        $stmt = $db->prepare(
            'INSERT INTO password_reset_rate_limits (ip_address, request_count, last_request_at) VALUES (:ip, 1, NOW())'
        );
        $stmt->execute([':ip' => $clientIp]);
    }

    $stmt = $db->prepare('SELECT email FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $db->prepare(
            'DELETE FROM frontend_password_resets WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);

        $stmt = $db->prepare(
            'INSERT INTO frontend_password_resets (email, token_hash, expires_at, used) VALUES (:email, :hash, :expires, 0)'
        );
        $stmt->execute([
            ':email'   => $email,
            ':hash'    => $tokenHash,
            ':expires' => $expiresAt,
        ]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
        $resetUrl = "$protocol://$host$basePath/reset-password.php?token=$rawToken";

        error_log("Reset URL generated: $resetUrl");

        $emailResult = EmailService::send([
            'to'      => $email,
            'subject' => 'Reset Your Password - INTAN ELYU',
            'html'    => buildEmailHtml($resetUrl),
            'altBody' => "Reset your INTAN ELYU password by visiting:\n$resetUrl\n\nThis link expires in 30 minutes.",
        ]);

        if (!$emailResult['success']) {
            $emailResult = sendResendDirect($email, 'Reset Your Password - INTAN ELYU', buildEmailHtml($resetUrl));
        }

        echo json_encode([
            'success'      => true,
            'message'      => 'If an account exists with that email, a password reset link has been sent.',
            'reset_url'    => $resetUrl,
            'email_sent'   => $emailResult['success'],
            'email_error'  => $emailResult['success'] ? null : ($emailResult['error'] ?? 'Email delivery failed.'),
            'sender_email' => $emailResult['sender_email'] ?? ($emailResult['success'] ? 'onboarding@resend.dev' : null),
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log('Forgot password error: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => 'If an account exists with that email, a password reset link has been sent.',
]);

// ─────────────────────────────────────────────────────────────────────────────
// Direct Resend API fallback (bypasses EmailService entirely)
// ─────────────────────────────────────────────────────────────────────────────
function sendResendDirect(string $to, string $subject, string $html): array
{
    $envFile = __DIR__ . '/../../../../backend/.env';
    $apiKey  = getResendApiKey();

    // Direct .env parse if getResendApiKey failed
    if (!$apiKey && file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^RESEND_API_KEY\s*=\s*(.+)/', $line, $m)) {
                $apiKey = trim($m[1], '"\' ');
                break;
            }
        }
    }

    if (!$apiKey) {
        error_log('sendResendDirect: No API key found');
        return ['success' => false, 'error' => 'API key not found', 'sender_email' => null];
    }

    $payload = json_encode([
        'from'    => 'INTAN ELYU <onboarding@resend.dev>',
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    error_log("sendResendDirect: HTTP $httpCode, error: " . ($curlErr ?: 'none'));

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'error' => null, 'sender_email' => 'onboarding@resend.dev'];
    }

    $msg = "Resend HTTP $httpCode";
    if ($response) {
        $decoded = json_decode($response, true);
        if (!empty($decoded['message'])) {
            $msg = $decoded['message'];
        }
    }
    return ['success' => false, 'error' => $msg, 'sender_email' => null];
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared HTML email template
// ─────────────────────────────────────────────────────────────────────────────
function buildEmailHtml(string $resetUrl): string
{
    return <<<HTML
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
                <h2 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#1E293B;">Reset Your Password</h2>
                <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#4B5563;">
                    You requested a password reset for your INTAN ELYU account. Click the button below to set a new password.
                    <strong>This link expires in 30 minutes</strong> and can only be used once.
                </p>
                <div style="text-align:center;margin-bottom:24px;">
                    <a href="$resetUrl"
                       style="display:inline-block;background:linear-gradient(135deg,#06444D,#0D6557);color:#FFFFFF;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:15px;font-weight:600;">
                        Reset Password
                    </a>
                </div>
                <p style="margin:0 0 8px;font-size:12px;color:#9CA3AF;">
                    Or copy this link into your browser:
                </p>
                <p style="margin:0;font-size:12px;color:#6B7280;word-break:break-all;background:#F8FAFC;padding:10px;border-radius:8px;border:1px solid #E5E7EB;">
                    $resetUrl
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 32px 24px;border-top:1px solid #E5E7EB;">
                <p style="margin:0;font-size:12px;color:#9CA3AF;">
                    If you did not request this, please ignore this email. Your account remains secure.
                </p>
                <p style="margin:12px 0 0;font-size:11px;color:#D1D5DB;">
                    &copy; 2026 City Tourism Office • San Fernando City, La Union
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
