<?php

namespace App\Http\Controllers\Tourist;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\TouristSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * POST /api/tourist/destinations/{id}/favorite
     * Toggle a destination in/out of the user's saved places.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $spot = TouristSpot::findOrFail($id);

        $existing = Favorite::where('user_id', $user->id)
            ->where('tourist_spot_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['status' => 'removed', 'message' => 'Removed from Saved Places.']);
        }

        Favorite::create([
            'user_id'         => $user->id,
            'tourist_spot_id' => $id,
        ]);

        return response()->json(['status' => 'added', 'message' => 'Added to Saved Places! ❤️']);
    }
}
