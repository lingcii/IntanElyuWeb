<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $userName = $request->session()->get('user_name', 'Unknown');

        ActivityLogService::log(
            ActivityAction::LOGOUT,
            'Users',
            'User "' . $userName . '" logged out',
            null,
            null,
            $request
        );

        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }
}
