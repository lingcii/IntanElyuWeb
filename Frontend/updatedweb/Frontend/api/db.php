<?php

/**
 * Centralized PDO database connection helper.
 * Reads credentials from environment variables, with fallback to parsing the
 * Laravel backend .env file for local development.
 */

function getEnvValue(string $key): ?string
{
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }

    if (!empty($_ENV[$key])) {
        return $_ENV[$key];
    }

    return null;
}

function parseEnvFile(string $path): array
{
    $vars = [];
    if (!file_exists($path)) {
        return $vars;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        $val = trim($val, '"\'');
        $vars[$key] = $val;
    }
    return $vars;
}

function parseDatabaseUrl(string $url): ?array
{
    $parsed = parse_url($url);
    if (!$parsed || empty($parsed['host'])) {
        return null;
    }
    return [
        'DB_HOST'     => $parsed['host'],
        'DB_PORT'     => $parsed['port'] ?? '3306',
        'DB_DATABASE' => ltrim($parsed['path'] ?? '', '/'),
        'DB_USERNAME' => $parsed['user'] ?? '',
        'DB_PASSWORD' => $parsed['pass'] ?? '',
    ];
}

function getDbCredentials(): array
{
    $envPath = __DIR__ . '/../../../../backend/.env';
    $envVars = [];

    $databaseUrl = getEnvValue('DATABASE_URL');
    if ($databaseUrl) {
        $envVars = parseDatabaseUrl($databaseUrl) ?? [];
    }

    if (empty($envVars['DB_HOST']) && file_exists($envPath)) {
        $envVars = parseEnvFile($envPath);
    }

    $databaseUrl = $envVars['DATABASE_URL'] ?? null;
    if ($databaseUrl) {
        $parsed = parseDatabaseUrl($databaseUrl);
        if ($parsed) {
            $envVars = array_merge($envVars, $parsed);
        }
    }

    return [
        'host'     => getEnvValue('DB_HOST')     ?? $envVars['DB_HOST']     ?? '127.0.0.1',
        'port'     => getEnvValue('DB_PORT')     ?? $envVars['DB_PORT']     ?? '3306',
        'database' => getEnvValue('DB_DATABASE') ?? $envVars['DB_DATABASE'] ?? 'intan_elyu',
        'username' => getEnvValue('DB_USERNAME') ?? $envVars['DB_USERNAME'] ?? 'root',
        'password' => getEnvValue('DB_PASSWORD') ?? $envVars['DB_PASSWORD'] ?? '',
    ];
}

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $creds = getDbCredentials();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $creds['host'],
        $creds['port'],
        $creds['database']
    );

    $pdo = new PDO($dsn, $creds['username'], $creds['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    runMigrations($pdo);

    return $pdo;
}

function runMigrations(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS frontend_password_resets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_email (email),
            INDEX idx_token_hash (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_rate_limits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            request_count INT UNSIGNED NOT NULL DEFAULT 1,
            last_request_at DATETIME NOT NULL,
            INDEX idx_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_sender_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) DEFAULT '',
            app_password VARCHAR(512) NOT NULL,
            priority INT UNSIGNED NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            emails_sent BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_active_default (is_active, is_default),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id BIGINT UNSIGNED DEFAULT 0,
            sender_email VARCHAR(255) DEFAULT '',
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            method VARCHAR(30) DEFAULT '',
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recipient (recipient),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function getResendApiKey(): ?string
{
    $key = getEnvValue('RESEND_API_KEY');
    if ($key) {
        return $key;
    }

    $envPath = __DIR__ . '/../../../../backend/.env';
    if (file_exists($envPath)) {
        $vars = parseEnvFile($envPath);
        if (!empty($vars['RESEND_API_KEY'])) {
            return $vars['RESEND_API_KEY'];
        }
    }

    return null;
}

function getGmailCredentials(): array
{
    $user     = getEnvValue('GMAIL_APP_USER');
    $password = getEnvValue('GMAIL_APP_PASSWORD');

    if ($user && $password && $password !== 'your_gmail_app_password_here') {
        return ['user' => $user, 'password' => $password];
    }

    $envPath = __DIR__ . '/../../../../backend/.env';
    if (file_exists($envPath)) {
        $vars = parseEnvFile($envPath);
        $u    = $vars['GMAIL_APP_USER']     ?? '';
        $p    = $vars['GMAIL_APP_PASSWORD'] ?? '';
        if ($u && $p && $p !== 'your_gmail_app_password_here') {
            return ['user' => $u, 'password' => $p];
        }
    }

    return [];
}
