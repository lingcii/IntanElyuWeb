<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate mobile tourist requests via Bearer token stored in users.api_token.
 *
 * Fix #4: Tokens are stored as SHA-256 hashes. The incoming plain token is
 * hashed before lookup to prevent DB-leaked tokens from being reused.
 */
class TouristAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (!$plainToken) {
            return response()->json(['error' => 'Unauthorized: no token provided.'], 401);
        }

        // Compare hash of incoming token against the stored hash
        $hashedToken = hash('sha256', $plainToken);
        $user = User::where('api_token', $hashedToken)->first();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized: invalid token.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['error' => 'Account is inactive.'], 403);
        }

        // Bind user to request so controllers can access it
        $request->merge(['_tourist_user' => $user]);
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}
