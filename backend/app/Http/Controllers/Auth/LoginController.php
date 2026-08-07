<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->email;

        // ── Account lockout: per-email + per-IP rate limiter ─────────────────
        $rateLimitKey = 'login:' . Str::lower($loginInput) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'error' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::with('municipality:id,name')
            ->where('email', $loginInput)
            ->orWhere('name', $loginInput)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Count this failed attempt
            RateLimiter::hit($rateLimitKey, 60);

            ActivityLogService::log(
                ActivityAction::LOGIN_FAILED,
                'Users',
                'Failed login attempt for "' . $loginInput . '"',
                null,
                ['attempted_login' => $loginInput],
                $request
            );
            return response()->json(['error' => 'Invalid credentials.'], 401);
        }

        if ($user->status !== 'active') {
            if ($user->status === 'pending' && $user->is_default_password) {
                // First-time login: allow authenticating to change password
            } else {
                ActivityLogService::log(
                    ActivityAction::LOGIN_FAILED,
                    'Users',
                    'Login blocked for inactive account "' . $user->name . '"',
                    null,
                    ['status' => $user->status],
                    $request
                );
                $errorMsg = $user->status === 'pending' ? 'Account activation is pending.' : 'Account is inactive.';
                return response()->json(['error' => $errorMsg], 403);
            }
        }

        // Clear failed-attempt counter on successful login
        RateLimiter::clear($rateLimitKey);

        // Generate a one-time sync token for sync-session.php to verify.
        // This prevents unauthenticated users from forging PHP sessions by posting
        // directly to sync-session.php with arbitrary user data.
        $syncToken = Str::random(40);

        // Store session
        $request->session()->put('user_id',              $user->id);
        $request->session()->put('user_name',            $user->name);
        $request->session()->put('user_email',           $user->email);
        $request->session()->put('user_role',            $user->role);
        $request->session()->put('user_municipality_id', $user->municipality_id);
        $request->session()->put('must_change_password',  $user->is_default_password ? true : false);
        $request->session()->put('_pending_sync_token',  $syncToken); // consumed by sync-session.php
        if ($user->is_default_password) {
            $request->session()->put('just_logged_in', true);
        }
        $request->session()->regenerate();

        ActivityLogService::log(
            ActivityAction::LOGIN,
            'Users',
            'User "' . $user->name . '" logged in successfully',
            null,
            ['session_id' => session()->getId()],
            $request
        );

        // Update last activity
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['last_activity' => now()]);

        return response()->json([
            'success'     => true,
            '_sync_token' => $syncToken, // passed to sync-session.php, consumed once
            'user'    => [
                'id'                   => $user->id,
                'name'                 => $user->name,
                'email'                => $user->email,
                'role'                 => $user->role,
                'municipality_id'      => $user->municipality_id,
                'municipality_name'    => $user->municipality?->name,
                'must_change_password' => $user->is_default_password ? true : false,
            ],
        ]);
    }
}
