<?php
/**
 * Bridges PHP frontend sessions with the Laravel API (port 8000).
 * Stores Laravel session cookies server-side so XAMPP (port 80) can call the API.
 */

function getLaravelApiHost(): string
{
    return '127.0.0.1';
}

function getLaravelApiCookieString(): string
{
    if (!empty($_SESSION['laravel_cookie'])) {
        return $_SESSION['laravel_cookie'];
    }

    $cookieStr = '';
    foreach ($_COOKIE as $name => $value) {
        $cookieStr .= $name . '=' . urlencode($value) . '; ';
    }

    return $cookieStr;
}

function establishLaravelSession(string $email, string $password): bool
{
    $apiHost = getLaravelApiHost();
    $cookies = [];

    $ch = curl_init("http://{$apiHost}:8000/api/auth/login");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'email'    => $email,
            'password' => $password,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$cookies) {
            if (stripos($headerLine, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($headerLine, 11));
                $parts = explode(';', $cookie);
                if (!empty($parts[0])) {
                    $cookies[] = trim($parts[0]);
                }
            }
            return strlen($headerLine);
        },
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($cookies)) {
        return false;
    }

    $_SESSION['laravel_cookie'] = implode('; ', $cookies) . '; ';
    return true;
}
