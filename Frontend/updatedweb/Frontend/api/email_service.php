<?php

/**
 * Centralized Multi-Sender Email Service
 *
 * Supports:
 *   - Multiple Gmail sender accounts from database
 *   - Sending strategies: round-robin, random, priority-fallback
 *   - Automatic fallback on failure
 *   - Email logging
 *   - PHPMailer for SMTP, Resend API as ultimate fallback
 *
 * Usage:
 *   require_once __DIR__ . '/email_service.php';
 *   $result = EmailService::send([
 *       'to'      => 'user@gmail.com',
 *       'subject' => 'Welcome to INTAN ELYU',
 *       'html'    => '<h1>Hello!</h1>',
 *   ]);
 */

require_once __DIR__ . '/db.php';

$phpmailerBase = __DIR__ . '/../lib/phpmailer/';
if (file_exists($phpmailerBase . 'PHPMailer.php')) {
    require_once $phpmailerBase . 'Exception.php';
    require_once $phpmailerBase . 'SMTP.php';
    require_once $phpmailerBase . 'PHPMailer.php';
}

class EmailService
{
    const STRATEGY_ROUND_ROBIN   = 'round_robin';
    const STRATEGY_RANDOM        = 'random';
    const STRATEGY_PRIORITY      = 'priority';

    private static $roundRobinIndex = 0;

    /**
     * Send an email using the configured strategy.
     *
     * @param array $opts  [ 'to', 'subject', 'html', 'altBody' (optional), 'fromName' (optional) ]
     * @return array       [ 'success' => bool, 'sender_id' => ?int, 'sender_email' => ?string, 'error' => ?string ]
     */
    public static function send(array $opts): array
    {
        $to       = $opts['to']       ?? '';
        $subject  = $opts['subject']  ?? '';
        $html     = $opts['html']     ?? '';
        $altBody  = $opts['altBody']  ?? '';
        $fromName = $opts['fromName'] ?? 'INTAN ELYU Tourism';

        if (empty($to) || empty($subject) || empty($html)) {
            return self::logFailure(0, '', $to, $subject, 'Missing required fields (to, subject, html)');
        }

        $db = getDb();

        // Get all ACTIVE sender accounts, ordered by priority
        $stmt = $db->query(
            'SELECT id, email, app_password, priority, name
             FROM email_sender_accounts
             WHERE is_active = 1
             ORDER BY priority ASC, id ASC'
        );
        $senders = $stmt->fetchAll();

        if (empty($senders)) {
            // Auto-seed: if .env Gmail credentials exist, add them as a sender account
            $envCreds = getGmailCredentials();
            if (!empty($envCreds['user']) && !empty($envCreds['password'])) {
                self::autoSeedSender($db, $envCreds['user'] . ' (env)', $envCreds['user'], $envCreds['password'], 1);
                return self::send($opts); // Retry with the newly seeded sender
            }

            // No DB senders and no .env Gmail — try Resend as last resort
            $result = self::sendViaResend($to, $fromName, $subject, $html);
            if ($result['success']) {
                self::writeLog(0, 'onboarding@resend.dev', $to, $subject, 'sent', 'resend');
                return self::successResult(0, 'onboarding@resend.dev');
            }
            return self::logFailure(0, 'resend', $to, $subject, 'No sender accounts configured. Resend fallback also failed: ' . ($result['error'] ?: 'unknown'));
        }

        // Get sending strategy
        $strategy = self::getStrategy();
        $orderedSenders = $senders;

        if ($strategy === self::STRATEGY_RANDOM) {
            shuffle($orderedSenders);
        } elseif ($strategy === self::STRATEGY_ROUND_ROBIN) {
            $total = count($orderedSenders);
            $idx = self::$roundRobinIndex % $total;
            self::$roundRobinIndex++;
            if ($idx > 0) {
                $orderedSenders = array_merge(
                    array_slice($orderedSenders, $idx),
                    array_slice($orderedSenders, 0, $idx)
                );
            }
        }

        // Try each sender in order
        $errors = [];
        foreach ($orderedSenders as $sender) {
            $decryptedPassword = self::decrypt($sender['app_password']);
            if (empty($decryptedPassword)) {
                $errors[] = "Sender #{$sender['id']} ({$sender['email']}): missing or invalid password";
                continue;
            }

            $result = self::sendViaGmail(
                $sender['email'],
                $decryptedPassword,
                $to,
                $sender['name'] ?: $fromName,
                $subject,
                $html,
                $altBody
            );

            if ($result['success']) {
                self::writeLog($sender['id'], $sender['email'], $to, $subject, 'sent', 'gmail_smtp');
                self::incrementSentCount($sender['id']);
                return self::successResult($sender['id'], $sender['email']);
            }

            $errors[] = "Sender #{$sender['id']} ({$sender['email']}): " . ($result['error'] ?? 'Unknown error');
            self::writeLog($sender['id'], $sender['email'], $to, $subject, 'failed', 'gmail_smtp');
        }

        // All DB senders failed — try Resend as ultimate fallback
        $resendResult = self::sendViaResend($to, $fromName, $subject, $html);
        if ($resendResult['success']) {
            self::writeLog(0, 'onboarding@resend.dev', $to, $subject, 'sent', 'resend');
            return self::successResult(0, 'onboarding@resend.dev');
        }

        $allErrors = implode(' | ', $errors);
        return self::logFailure(0, '', $to, $subject, "All senders failed. " . $allErrors);
    }

    // ── Sending Methods ────────────────────────────────────────────────────────

    private static function sendViaGmail(
        string $username,
        string $password,
        string $to,
        string $fromName,
        string $subject,
        string $html,
        string $altBody = ''
    ): array {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return ['success' => false, 'error' => 'PHPMailer not loaded'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->Timeout = 15;

            $mail->setFrom($username, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($username, $fromName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            if ($altBody) {
                $mail->AltBody = $altBody;
            }

            $mail->send();
            error_log("Email sent via Gmail SMTP ($username) to $to");
            return ['success' => true, 'error' => ''];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Gmail SMTP error ($username): " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function sendViaResend(
        string $to,
        string $fromName,
        string $subject,
        string $html
    ): array {
        $apiKey = getResendApiKey();
        if (!$apiKey) {
            // Try direct .env read as fallback
            $envFile = __DIR__ . '/../../../../backend/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (preg_match('/^RESEND_API_KEY\s*=\s*(.+)/', $line, $m)) {
                        $apiKey = trim($m[1], '"\' ');
                        break;
                    }
                }
            }
        }
        if (!$apiKey) {
            error_log('Resend: No API key found');
            return ['success' => false, 'error' => 'RESEND_API_KEY not configured'];
        }

        $payload = json_encode([
            'from'    => "$fromName <onboarding@resend.dev>",
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
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Email sent via Resend to $to");
            return ['success' => true, 'error' => ''];
        }

        $msg = "Resend API returned HTTP $httpCode";
        if ($response) {
            $decoded = json_decode($response, true);
            if (!empty($decoded['message'])) {
                $msg = $decoded['message'];
            }
        }
        error_log("Resend error: $msg");
        return ['success' => false, 'error' => $msg];
    }

    // ── Strategy ───────────────────────────────────────────────────────────────

    private static function getStrategy(): string
    {
        $env = getEnvValue('EMAIL_STRATEGY');
        if ($env && in_array($env, [self::STRATEGY_ROUND_ROBIN, self::STRATEGY_RANDOM, self::STRATEGY_PRIORITY])) {
            return $env;
        }
        return self::STRATEGY_PRIORITY;
    }

    // ── Logging ────────────────────────────────────────────────────────────────

    private static function writeLog(
        int $senderId,
        string $senderEmail,
        string $to,
        string $subject,
        string $status,
        string $method
    ): void {
        try {
            $db = getDb();
            $stmt = $db->prepare(
                'INSERT INTO email_logs (sender_id, sender_email, recipient, subject, status, method, created_at)
                 VALUES (:sender_id, :sender_email, :recipient, :subject, :status, :method, NOW())'
            );
            $stmt->execute([
                ':sender_id'    => $senderId,
                ':sender_email' => $senderEmail,
                ':recipient'    => $to,
                ':subject'      => $subject,
                ':status'       => $status,
                ':method'       => $method,
            ]);
        } catch (\Exception $e) {
            error_log('Email log write error: ' . $e->getMessage());
        }
    }

    private static function incrementSentCount(int $senderId): void
    {
        try {
            $db = getDb();
            $db->prepare(
                'UPDATE email_sender_accounts SET emails_sent = emails_sent + 1 WHERE id = :id'
            )->execute([':id' => $senderId]);
        } catch (\Exception $e) {
            error_log('Email sent count increment error: ' . $e->getMessage());
        }
    }

    private static function autoSeedSender(\PDO $db, string $name, string $email, string $password, int $priority): void
    {
        $existing = $db->prepare('SELECT id FROM email_sender_accounts WHERE email = :email');
        $existing->execute([':email' => $email]);
        if ($existing->fetch()) return;

        $encrypted = self::encrypt($password);
        $db->prepare(
            'INSERT INTO email_sender_accounts (email, name, app_password, priority, is_active, is_default, created_at)
             VALUES (:email, :name, :pw, :pri, 1, 1, NOW())'
        )->execute([':email' => $email, ':name' => $name, ':pw' => $encrypted, ':pri' => $priority]);
    }

    // ── Result Helpers ──────────────────────────────────────────────────────────

    private static function successResult(int $id, string $email): array
    {
        return [
            'success'      => true,
            'sender_id'    => $id,
            'sender_email' => $email,
            'error'        => null,
        ];
    }

    private static function logFailure(int $id, string $email, string $to, string $subject, string $error): array
    {
        error_log("Email failure to $to: $error");
        if ($id || $email) {
            self::writeLog($id, $email, $to, $subject, 'failed', 'unknown');
        } else {
            self::writeLog(0, '', $to, $subject, 'failed', 'unknown');
        }
        return [
            'success'      => false,
            'sender_id'    => null,
            'sender_email' => null,
            'error'        => $error,
        ];
    }

    // ── Crypto Helpers (AES-256-CBC with key derived from DB credentials) ──────

    private static function getEncryptionKey(): string
    {
        $creds = getDbCredentials();
        $raw = $creds['host'] . $creds['database'] . 'INTAN_ELYU_SALT_2026';
        return hash('sha256', $raw, true);
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv  = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $key  = self::getEncryptionKey();
        $data = base64_decode($encoded);
        if ($data === false || strlen($data) < 16) {
            return '';
        }
        $iv         = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $result     = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $result !== false ? $result : '';
    }

    /**
     * Validate Gmail credentials by attempting SMTP connection (no email sent).
     */
    public static function validateCredentials(string $email, string $password): array
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return ['valid' => false, 'error' => 'PHPMailer not loaded'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $email;
            $mail->Password   = $password;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->SMTPOptions = [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
            ];
            $mail->Timeout = 10;
            $mail->SMTPDebug = 0;

            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return ['valid' => true, 'error' => ''];
            }
            return ['valid' => false, 'error' => 'SMTP connection failed'];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
