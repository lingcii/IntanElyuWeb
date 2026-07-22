<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limit authentication endpoints (login, register) to prevent
 * brute-force and credential-stuffing attacks.
 *
 * Limits: 5 attempts per IP per 60 seconds.
 * On exceeded: returns 429 Too Many Requests with a Retry-After header.
 */
class ThrottleAuthRequests
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decaySeconds = 60): Response
    {
        // Key = route name + client IP so each endpoint is tracked separately
        $key = 'auth_throttle:' . $request->path() . ':' . $request->ip();

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);

            return response()->json([
                'error'       => 'Too many attempts. Please wait before trying again.',
                'retry_after' => $seconds,
            ], 429, [
                'Retry-After'       => $seconds,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        // Register this hit
        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        // If auth succeeds (2xx) on login, clear the limiter so the user
        // isn't penalised for a correct credential after earlier failures.
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->limiter->clear($key);
        }

        // Attach informational rate-limit headers
        $response->headers->set('X-RateLimit-Limit',     $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $this->limiter->attempts($key)));

        return $response;
    }
}
