<?php

namespace App\Http\Controllers\Tourist;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\ItineraryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * GET /api/tourist/profile
     * Returns detailed profile data for the tourist mobile app.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Calculate Rank using CTE from Leaderboard
        $rankData = DB::selectOne("
            WITH ranked AS (
                SELECT
                    u.id AS user_id,
                    ROW_NUMBER() OVER (
                        ORDER BY
                            COALESCE(up.total_points, 0) DESC,
                            COALESCE(up.completed_activities, 0) DESC,
                            COALESCE(up.points_since, u.created_at) ASC
                    ) AS user_rank
                FROM users u
                LEFT JOIN user_points up ON up.user_id = u.id
                WHERE u.role = 'tourist' AND u.status = 'active'
            )
            SELECT user_rank FROM ranked WHERE user_id = ?
        ", [$user->id]);

        $myRank = $rankData ? (int) $rankData->user_rank : null;

        // 2. Places Visited
        $placesVisited = ItineraryItem::whereHas('itinerary', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('is_visited', true)->count();

        // 3. Completed Trips (Trip History)
        $completedTrips = Itinerary::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with('items')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($trip) {
                return [
                    'id' => $trip->id,
                    'title' => $trip->title,
                    'trip_date' => $trip->trip_date ? $trip->trip_date->format('Y-m-d') : null,
                    'total_cost' => $trip->total_cost,
                    'items' => $trip->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'is_visited' => $item->is_visited
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'xp' => (int) ($user->xp ?? 0),
                'avatar' => $user->avatar
            ],
            'places_visited' => $placesVisited,
            'my_rank' => $myRank,
            'completed_trips' => $completedTrips
        ]);
    }
}
