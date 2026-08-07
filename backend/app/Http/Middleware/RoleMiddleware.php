<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check that the authenticated user has one of the allowed roles.
 *
 * Usage in routes:  ->middleware('role:picto')
 *                   ->middleware('role:picto,lupto')
 *                   ->middleware('role:municipal')   (matches all *_mto + 'municipal')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->session()->has('user_id')) {
            return response()->json(['error' => 'Unauthorized: not logged in'], 401);
        }

        $userRole = $request->session()->get('user_role');
        if ($userRole === 'pitco') {
            $userRole = 'picto';
        }

        // Expand 'municipal' shorthand to include all MTO roles
        $expanded = [];
        foreach ($roles as $role) {
            if ($role === 'municipal') {
                $expanded = array_merge($expanded, \App\Models\User::$MUNICIPAL_ROLES);
            } else {
                $expanded[] = $role;
            }
        }

        if (!in_array($userRole, $expanded)) {
            // Fix #8: Generic message only — don't leak required or current role info
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
