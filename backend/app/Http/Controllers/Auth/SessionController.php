<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * GET /api/auth/check
     */
    public function check(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_id')) {
            return response()->json(['authenticated' => false], 401);
        }

        $municipalityId = $request->session()->get('user_municipality_id');
        $municipalityName = null;
        if ($municipalityId) {
            $municipality = Municipality::select('name')->find($municipalityId);
            $municipalityName = $municipality?->name;
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'                => $request->session()->get('user_id'),
                'name'              => $request->session()->get('user_name'),
                'email'             => $request->session()->get('user_email'),
                'role'              => $request->session()->get('user_role'),
                'municipality_id'   => $municipalityId,
                'municipality_name' => $municipalityName,
            ],
        ]);
    }
}
