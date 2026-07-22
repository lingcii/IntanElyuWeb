<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Allows the website frontend (port 8080) and mobile frontend (port 3000)
    | to call the Laravel API (http://127.0.0.1:8000).
    |
    | Both frontends run as separate processes:
    |   - Mobile PHP server:  php -S localhost:3000   (Mobile)
    |   - Website PHP server: php -S localhost:8080   (Website)
    |   - Laravel:            php artisan serve        (Backend, port 8000)
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Mobile PHP built-in dev server (port 3000)
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // Website PHP built-in dev server (port 8080)
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:8081',
        'http://127.0.0.1:8081',

        // Laravel itself (for same-origin requests / Artisan serve)
        'http://localhost:8000',
        'http://127.0.0.1:8000',

        // Generic localhost (no port)
        'http://localhost',
        'http://127.0.0.1',

        // Cloudflare tunnel (remote access / staging)
        'https://boc-cornell-rolled-delicious.trycloudflare.com',
    ],

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    /*
     * IMPORTANT: credentials must be true so the session cookie is sent
     * with every cross-origin request from the frontend dev servers.
     * The allowed_origins list above must NOT use a wildcard when this
     * is true � each origin must be listed explicitly.
     */
    'supports_credentials' => true,
];
