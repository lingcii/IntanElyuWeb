<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders
 *
 * Fix #7: Adds essential HTTP security response headers to every request.
 * Protects against clickjacking (X-Frame-Options), MIME-sniffing
 * (X-Content-Type-Options), information leakage (Referrer-Policy),
 * and enforces HTTPS (HSTS).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent the page from being loaded in an iframe (clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent browser from MIME-sniffing the response content type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control how much referrer info is sent with requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Enforce HTTPS for 1 year (only effective on HTTPS deployments)
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Restrict powerful browser features
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=()'
        );

        // Basic Content Security Policy — adjust as needed for your CDN/font sources
        $response->headers->set(
            'X-XSS-Protection',
            '1; mode=block'
        );

        return $response;
    }
}
