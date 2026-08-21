<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check that the authenticated user has one of the allowed roles.
 *
 * Usage in routes:  ->middleware('role:picto')
 *                   ->middleware('role:picto,lupto')
 *                   ->middleware('role:municipal')   (matches all *_mto + 'municipal')
 *
 * Also enforces maintenance mode: LUPTO and Municipal users are blocked with 503
 * while maintenance mode is active. PICTO is always allowed through.
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

        // ── Maintenance Mode enforcement ──────────────────────────────────────
        // PICTO is always allowed through. All other roles are blocked during maintenance.
        $isPicto = in_array($userRole, ['picto', 'pitco']);
        if (!$isPicto && Cache::has('maintenance_mode')) {
            $maintenanceData = Cache::get('maintenance_mode');
            return response()->json([
                'error'        => 'System under maintenance.',
                'maintenance'  => true,
                'activated_at' => $maintenanceData['activated_at'] ?? null,
                'message'      => 'The system is currently under maintenance. Please try again later.',
            ], 503);
        }

        return $next($request);
    }
}
