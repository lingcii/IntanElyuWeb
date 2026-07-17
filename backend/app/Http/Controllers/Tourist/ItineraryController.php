<?php

namespace App\Http\Controllers\Tourist;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\TouristSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryController extends Controller
{
    /**
     * GET /api/tourist/itineraries
     * Returns all saved trips for the authenticated tourist.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $itineraries = Itinerary::where('user_id', $user->id)
            ->with(['items.destination:id,name,photo_url,barangay,latitude,longitude,entrance_fee'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($itinerary) {
                $items = $itinerary->items->map(function ($item) {
                    $dest = $item->destination;
                    $imageUrl = null;
                    if ($dest && $dest->photo_url) {
                        $imageUrl = str_starts_with($dest->photo_url, 'http')
                            ? $dest->photo_url
                            : 'http://localhost:8000/storage/' . $dest->photo_url;
                    }

                    return [
                        'id'          => $item->id,
                        'is_visited'  => $item->is_visited,
                        'proof_image' => $item->proof_image,
                        'visited_at'  => $item->visited_at,
                        'destination' => $dest ? [
                            'id'           => $dest->id,
                            'name'         => $dest->name,
                            'location'     => $dest->barangay,
                            'image'        => $imageUrl,
                            'latitude'     => $dest->latitude,
                            'longitude'    => $dest->longitude,
                            'entrance_fee' => $dest->entrance_fee,
                        ] : null,
                    ];
                });

                return [
                    'id'         => $itinerary->id,
                    'title'      => $itinerary->title,
                    'trip_date'  => $itinerary->trip_date?->format('Y-m-d'),
                    'budget'     => $itinerary->budget,
                    'total_cost' => $itinerary->total_cost,
                    'status'     => $itinerary->status,
                    'items'      => $items,
                ];
            });

        return response()->json(['itineraries' => $itineraries]);
    }

    /**
     * POST /api/tourist/itineraries
     * Save a draft plan as a named itinerary.
     *
     * Body:
     *   - title (required)
     *   - destinations (array of spot IDs, required)
     *   - trip_date (optional)
     *   - budget (optional)
     *   - transport (optional)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'destinations'   => 'required|array|min:1',
            'destinations.*' => 'integer|exists:tourist_spots,id',
            'trip_date'      => 'nullable|date',
            'budget'         => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();

        // Rough cost estimation based on entrance fees
        $spots = TouristSpot::whereIn('id', $request->destinations)->get();
        $totalFee = $spots->sum('entrance_fee');

        $itinerary = Itinerary::create([
            'user_id'    => $user->id,
            'title'      => $request->title,
            'trip_date'  => $request->trip_date,
            'budget'     => $request->budget,
            'total_cost' => $totalFee,
            'status'     => 'pending',
        ]);

        // Create itinerary items preserving order
        foreach ($request->destinations as $spotId) {
            ItineraryItem::create([
                'itinerary_id'    => $itinerary->id,
                'tourist_spot_id' => $spotId,
            ]);
        }

        return response()->json([
            'message'      => 'Trip saved! 🎉',
            'itinerary_id' => $itinerary->id,
        ], 201);
    }

    /**
     * PATCH /api/tourist/itineraries/{id}/complete
     * Mark an itinerary as completed.
     */
    public function markCompleted(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $itinerary = Itinerary::where('user_id', $user->id)->findOrFail($id);

        $itinerary->update(['status' => 'completed']);

        return response()->json(['message' => 'Trip marked as completed! 🏁']);
    }
}
