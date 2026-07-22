<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate mobile tourist requests via Bearer token stored in users.api_token.
 */
class TouristAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthorized: no token provided.'], 401);
        }

        $user = User::where('api_token', $token)->first();

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
