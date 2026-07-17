<?php

namespace App\Http\Controllers\Tourist;

use App\Http\Controllers\Controller;
use App\Models\ItineraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryItemController extends Controller
{
    private const XP_PER_VISIT = 50;

    /**
     * Maximum allowed distance (in meters) for a valid GPS check-in.
     */
    private const MAX_DISTANCE_METERS = 300;

    /**
     * PATCH /api/tourist/itineraries/items/{id}/visit
     * Awards XP only if the user's GPS position is within MAX_DISTANCE_METERS
     * of the tourist spot's coordinates.
     */
    public function visit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $user = $request->user();

        $item = ItineraryItem::whereHas('itinerary', fn($q) => $q->where('user_id', $user->id))
            ->with('destination:id,name,latitude,longitude')
            ->findOrFail($id);

        if ($item->is_visited) {
            return response()->json(['message' => 'You have already checked in at this spot.'], 409);
        }

        $spot = $item->destination;

        if (!$spot || !$spot->latitude || !$spot->longitude) {
            return response()->json(['message' => 'This destination has no GPS coordinates set.'], 422);
        }

        // Haversine formula — great-circle distance between two GPS points
        $distanceMeters = $this->haversine(
            (float) $request->lat,
            (float) $request->lng,
            (float) $spot->latitude,
            (float) $spot->longitude
        );

        if ($distanceMeters > self::MAX_DISTANCE_METERS) {
            return response()->json([
                'message'  => "You're too far from {$spot->name}. Get closer to check in! 📍",
                'distance' => round($distanceMeters) . 'm away',
                'required' => self::MAX_DISTANCE_METERS . 'm',
            ], 403);
        }

        // GPS confirmed — mark as visited
        $item->update([
            'is_visited' => true,
            'visited_at' => now(),
        ]);

        // Increment the tourist spot's visit counter
        $item->destination()->increment('visits');

        // Award XP
        $newXp    = ($user->xp ?? 0) + self::XP_PER_VISIT;
        $newLevel = (int) floor($newXp / 1000) + 1;
        $user->update(['xp' => $newXp, 'level' => $newLevel]);

        return response()->json([
            'message'   => "You're here! +".self::XP_PER_VISIT." XP earned! 🌟",
            'xp_earned' => self::XP_PER_VISIT,
            'total_xp'  => $newXp,
            'new_level' => $newLevel,
            'distance'  => round($distanceMeters) . 'm',
        ]);
    }

    /**
     * Haversine formula — returns the distance in meters between two lat/lng points.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // metres

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
