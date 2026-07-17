<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /** GET /api/profile */
    public function show(Request $request): JsonResponse
    {
        $user = User::with('municipality:id,name')
            ->findOrFail((int) $request->session()->get('user_id'));

        return response()->json(['success' => true, 'user' => $user->makeHidden('password')]);
    }

    /** PUT /api/profile */
    public function update(Request $request): JsonResponse
    {
        $id = (int) $request->session()->get('user_id');

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$id}",
        ]);

        $user = User::findOrFail($id);
        $user->update(['name' => $request->name, 'email' => $request->email]);

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

    /** PUT /api/profile/password */
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

        $user->update(['password' => Hash::make($request->new_password)]);

        ActivityLogService::log(
            ActivityAction::PASSWORD_CHANGED,
            'Settings',
            'Password changed for "' . $user->name . '"',
            null,
            null,
            $request
        );
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }
}
