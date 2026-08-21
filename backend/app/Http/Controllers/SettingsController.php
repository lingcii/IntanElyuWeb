<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class SettingsController extends Controller
{
    /**
     * GET /api/{role}/settings/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = User::with('municipality:id,name')
            ->findOrFail((int) $request->session()->get('user_id'));

        return response()->json(['success' => true, 'user' => $user->makeHidden('password')]);
    }

    /**
     * PUT /api/{role}/settings/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $id = (int) $request->session()->get('user_id');
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$id}",
        ]);

        $user = User::findOrFail($id);
        $user->update(['name' => $request->name, 'email' => $request->email]);

        // Keep session in sync
        $request->session()->put('user_name',  $user->name);
        $request->session()->put('user_email', $user->email);

        ActivityLogService::log(
            ActivityAction::PROFILE_UPDATED,
            'Settings',
            'Profile updated for "' . $user->name . '"',
            ['name' => $user->getOriginal('name'), 'email' => $user->getOriginal('email')],
            ['name' => $request->name, 'email' => $request->email],
            $request
        );
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'message' => 'Profile updated.']);
    }

    /**
     * PUT /api/{role}/settings/password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = User::findOrFail((int) $request->session()->get('user_id'));

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $wasDefault = $user->is_default_password;
        $updates = ['password' => Hash::make($request->new_password)];

        if ($wasDefault) {
            $updates['is_default_password'] = false;
            if ($user->status === 'pending') {
                $updates['status'] = 'active';
            }
        }

        $user->update($updates);

        ActivityLogService::log(
            ActivityAction::PASSWORD_CHANGED,
            'Settings',
            'Password changed for "' . $user->name . '"',
            null,
            null,
            $request
        );
        Cache::forget('activity_stats');

        return response()->json([
            'success'          => true,
            'message'          => 'Password updated.',
            'first_time_reset' => $wasDefault ? true : false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Maintenance Mode (PICTO-only)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/pitco/settings/maintenance
     * GET /api/system/maintenance-status  (public)
     */
    public function getMaintenanceStatus(Request $request): JsonResponse
    {
        $data = Cache::get('maintenance_mode');

        if (!$data) {
            return response()->json([
                'success'     => true,
                'maintenance' => false,
            ]);
        }

        return response()->json([
            'success'      => true,
            'maintenance'  => true,
            'activated_at' => $data['activated_at'] ?? null,
            'activated_by' => $data['activated_by'] ?? null,
        ]);
    }

    /**
     * POST /api/pitco/settings/maintenance/activate
     */
    public function activateMaintenance(Request $request): JsonResponse
    {
        $userRole = $request->session()->get('user_role');
        if (!in_array($userRole, ['picto', 'pitco'])) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        Cache::forever('maintenance_mode', [
            'active'       => true,
            'activated_at' => Carbon::now()->toISOString(),
            'activated_by' => $request->session()->get('user_name', 'Unknown'),
        ]);
        Cache::forget('activity_stats');

        ActivityLogService::log(
            ActivityAction::MAINTENANCE_ACTIVATED,
            'Settings',
            'Maintenance mode activated by "' . $request->session()->get('user_name') . '"',
            null,
            ['status' => 'maintenance_active'],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Maintenance mode activated. LUPTO and Municipal users are now restricted.',
        ]);
    }

    /**
     * POST /api/pitco/settings/maintenance/deactivate
     */
    public function deactivateMaintenance(Request $request): JsonResponse
    {
        $userRole = $request->session()->get('user_role');
        if (!in_array($userRole, ['picto', 'pitco'])) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        Cache::forget('maintenance_mode');
        Cache::forget('activity_stats');

        ActivityLogService::log(
            ActivityAction::MAINTENANCE_DEACTIVATED,
            'Settings',
            'Maintenance mode deactivated by "' . $request->session()->get('user_name') . '"',
            null,
            ['status' => 'system_active'],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Maintenance mode deactivated. System access restored for all users.',
        ]);
    }
}
