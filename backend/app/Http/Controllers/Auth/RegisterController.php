<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * POST /api/auth/register
     * Registers a tourist account (self-registration).
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Fix #4: Store a SHA-256 hash of the token — never the plain token.
        // Return the plain token to the client only once (at registration).
        $plainToken = Str::random(60);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => 'tourist',
            'status'    => 'active',
            'api_token' => hash('sha256', $plainToken),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'token'   => $plainToken,  // plain token returned once — never stored
            'user'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'role'   => $user->role,
                'xp'     => 0,
                'level'  => 1,
                'avatar' => null,
            ],
        ], 201);
    }
}
