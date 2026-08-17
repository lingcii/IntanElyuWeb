<?php

namespace App\Http\Controllers\Tourist;

use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\TouristSpot;
use App\Models\UserPoint;
use App\Services\ActivityLogService;
use App\Services\CacheInvalidationService;
use App\Services\CloudflareR2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItineraryController extends Controller
{
    private const XP_PER_VISIT = 50;
    private const POINTS_PER_VISIT = 50;
    private const MAX_DISTANCE_METERS = 300; // Geofence radius

    /**
     * Record a visit in the analytics table.
     */
    private function recordSpotVisitAnalytics(TouristSpot $spot): void
    {
        try {
            $year = (int) date('Y');
            $month = (int) date('n');

            $existing = DB::table('analytics')->where([
                'municipality_id' => $spot->municipality_id,
                'tourist_spot_id' => $spot->id,
                'year' => $year,
                'month' => $month,
            ])->first();

            if ($existing) {
                DB::table('analytics')->where('id', $existing->id)->increment('visits');
            } else {
                DB::table('analytics')->insert([
                    'municipality_id' => $spot->municipality_id,
                    'tourist_spot_id' => $spot->id,
                    'year' => $year,
                    'month' => $month,
                    'visits' => 1,
                    'transport_car' => 1,
                    'transport_van' => 0,
                    'transport_bus' => 0,
                    'transport_other' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to record visit in analytics: " . $e->getMessage());
        }
    }

    /**
     * Award XP and Points to tourist for visiting a spot.
     */
    private function awardTouristVisitRewards($user, TouristSpot $spot, ?int $itemId = null): array
    {
        $earnedXp = self::XP_PER_VISIT;
        $earnedPoints = $spot->points > 0 ? $spot->points : self::POINTS_PER_VISIT;

        // Update User XP and Level
        $currentXp = (int) ($user->xp ?? 0);
        $newXp = $currentXp + $earnedXp;
        $newLevel = (int) floor($newXp / 1000) + 1;

        $user->update([
            'xp' => $newXp,
            'level' => $newLevel,
        ]);

        // Award Points
        try {
            UserPoint::firstOrCreate([
                'user_id' => $user->id,
                'source' => $itemId ? "visit_item_{$itemId}" : "visit_spot_{$spot->id}_" . date('Ymd'),
            ], [
                'points' => $earnedPoints,
                'description' => "Visited {$spot->name}",
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to award points for visit: " . $e->getMessage());
        }

        return [
            'xp_earned' => $earnedXp,
            'points_earned' => $earnedPoints,
            'total_xp' => $newXp,
            'level' => $newLevel,
        ];
    }

    /**
     * GET /api/tourist/itineraries
     * List tourist's itineraries with items and visit status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $itineraries = Itinerary::where('user_id', $user->id)
            ->with(['items.touristSpot.municipality'])
            ->orderByDesc('created_at')
            ->get();

        $formatted = $itineraries->map(function ($trip) {
            return [
                'id' => $trip->id,
                'name' => $trip->name ?? 'Trip Plan',
                'status' => $trip->status ?? 'pending',
                'route_type' => $trip->route_type ?? 'custom',
                'created_at' => $trip->created_at?->toIso8601String(),
                'items' => $trip->items->map(function ($item) {
                    $spot = $item->touristSpot;
                    return [
                        'id' => $item->id,
                        'tourist_spot_id' => $item->tourist_spot_id,
                        'is_visited' => (bool) $item->is_visited,
                        'proof_status' => $item->proof_status ?? ($item->is_visited ? 'approved' : 'pending'),
                        'proof_image' => $item->proof_image,
                        'visited_at' => $item->visited_at?->toIso8601String(),
                        'destination' => $spot ? [
                            'id' => $spot->id,
                            'name' => $spot->name,
                            'barangay' => $spot->barangay,
                            'category' => $spot->category,
                            'municipality' => $spot->municipality?->name,
                            'latitude' => (float) $spot->latitude,
                            'longitude' => (float) $spot->longitude,
                            'photo_url' => $spot->photo_url,
                            'points' => $spot->points,
                        ] : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * POST /api/tourist/itineraries
     * Create a new trip itinerary.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'nullable|string|max:255',
            'spot_ids' => 'required|array|min:1',
            'spot_ids.*' => 'integer|exists:tourist_spots,id',
            'route_type' => 'nullable|string|max:50',
        ]);

        $itinerary = Itinerary::create([
            'user_id' => $user->id,
            'name' => $request->input('name', 'My La Union Trip'),
            'status' => 'pending',
            'route_type' => $request->input('route_type', 'custom'),
        ]);

        foreach ($request->spot_ids as $spotId) {
            ItineraryItem::create([
                'itinerary_id' => $itinerary->id,
                'tourist_spot_id' => $spotId,
                'is_visited' => false,
                'proof_status' => 'pending',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Trip created successfully!',
            'itinerary' => $itinerary->load('items.touristSpot'),
        ], 201);
    }

    /**
     * PATCH/POST /api/tourist/itineraries/{id}/complete
     */
    public function completeTrip(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $trip = Itinerary::where('user_id', $user->id)->findOrFail($id);
        $trip->update(['status' => 'completed']);

        return response()->json([
            'status' => 'success',
            'message' => 'Trip marked as completed! 🎉',
        ]);
    }

    /**
     * POST/PATCH /api/tourist/itineraries/items/{id}/visit
     * Verifies visit via GPS distance, photo proof upload, or direct verification.
     */
    public function verifyItemVisit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $item = ItineraryItem::whereHas('itinerary', fn($q) => $q->where('user_id', $user->id))
            ->with(['touristSpot.municipality'])
            ->findOrFail($id);

        $spot = $item->touristSpot;
        if (!$spot) {
            return response()->json(['message' => 'Tourist destination not found.'], 404);
        }

        $method = $request->input('verification_method', 'gps');

        // ── 1. GPS Verification ───────────────────────────────────────────
        if ($method === 'gps') {
            $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lng' => 'required|numeric|between:-180,180',
            ]);

            $userLat = (float) $request->lat;
            $userLng = (float) $request->lng;

            // Update user last GPS location
            $user->update([
                'last_gps_lat' => $userLat,
                'last_gps_lng' => $userLng,
                'last_gps_ping_at' => now(),
            ]);

            if (!$spot->latitude || !$spot->longitude) {
                return response()->json(['message' => 'Destination GPS coordinates are not configured.'], 422);
            }

            $distanceMeters = $this->haversine($userLat, $userLng, (float) $spot->latitude, (float) $spot->longitude);

            if ($distanceMeters > self::MAX_DISTANCE_METERS) {
                return response()->json([
                    'message' => "You're {$distanceMeters}m away from {$spot->name}. Please get within " . self::MAX_DISTANCE_METERS . "m to check in!",
                    'distance' => round($distanceMeters) . 'm',
                    'required' => self::MAX_DISTANCE_METERS . 'm',
                ], 403);
            }

            // GPS Confirmed! Mark visited
            $item->update([
                'is_visited' => true,
                'visited_at' => now(),
                'proof_status' => 'approved',
            ]);

            // Increment visitor counter on spot
            $spot->increment('visits');

            // Record in analytics table
            $this->recordSpotVisitAnalytics($spot);

            // Invalidate caches so web dashboard updates
            CacheInvalidationService::invalidateAll($spot->municipality_id);

            // Award XP and points
            $reward = $this->awardTouristVisitRewards($user, $spot, $item->id);

            return response()->json([
                'status' => 'success',
                'message' => "You're here at {$spot->name}! +{$reward['xp_earned']} XP earned! 🌟",
                'is_visited' => true,
                'distance' => round($distanceMeters) . 'm',
                'rewards' => $reward,
            ]);
        }

        // ── 2. Photo Proof Upload ──────────────────────────────────────────
        if ($method === 'photo' || $request->hasFile('proof_photo')) {
            $request->validate([
                'proof_photo' => 'required|image|max:10240', // max 10MB
            ]);

            $file = $request->file('proof_photo');
            $proofUrl = null;

            // Attempt Cloudflare R2 upload first
            try {
                $r2 = new CloudflareR2Service();
                $filename = 'proof_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $proofUrl = $r2->uploadFile($file, 'proof_images/' . $filename);
            } catch (\Throwable $e) {
                Log::warning("R2 proof upload fallback to local: " . $e->getMessage());
            }

            if (!$proofUrl) {
                $path = $file->store('proofs', 'public');
                $proofUrl = rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/storage/' . $path;
            }

            $item->update([
                'proof_image' => $proofUrl,
                'proof_status' => 'pending',
                'visited_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Proof photo uploaded! Awaiting municipal validation. 📸",
                'proof_image' => $proofUrl,
                'is_visited' => false,
                'status_info' => 'pending_approval',
            ]);
        }

        // ── 3. Test / Direct Verification ──────────────────────────────────
        if ($method === 'test' || $method === 'direct') {
            $item->update([
                'is_visited' => true,
                'visited_at' => now(),
                'proof_status' => 'approved',
            ]);

            $spot->increment('visits');
            $this->recordSpotVisitAnalytics($spot);
            CacheInvalidationService::invalidateAll($spot->municipality_id);
            $reward = $this->awardTouristVisitRewards($user, $spot, $item->id);

            return response()->json([
                'status' => 'success',
                'message' => "Checked In at {$spot->name}! +{$reward['xp_earned']} XP earned! 🌟",
                'is_visited' => true,
                'rewards' => $reward,
            ]);
        }

        return response()->json(['message' => 'Invalid verification method.'], 422);
    }

    /**
     * POST /api/tourist/spots/{id}/visit
     * Direct spot check-in for mobile.
     */
    public function directSpotVisit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $spot = TouristSpot::where('status', 'approved')->findOrFail($id);

        if ($request->has('lat') && $request->has('lng')) {
            $userLat = (float) $request->lat;
            $userLng = (float) $request->lng;

            $user->update([
                'last_gps_lat' => $userLat,
                'last_gps_lng' => $userLng,
                'last_gps_ping_at' => now(),
            ]);

            if ($spot->latitude && $spot->longitude) {
                $dist = $this->haversine($userLat, $userLng, (float) $spot->latitude, (float) $spot->longitude);
                if ($dist > self::MAX_DISTANCE_METERS && !$request->boolean('force')) {
                    return response()->json([
                        'message' => "You're {$dist}m away from {$spot->name}. Please get closer to check in!",
                        'distance' => round($dist) . 'm',
                    ], 403);
                }
            }
        }

        // Increment visit counter
        $spot->increment('visits');

        // Record in analytics
        $this->recordSpotVisitAnalytics($spot);

        // Invalidate dashboard caches
        CacheInvalidationService::invalidateAll($spot->municipality_id);

        // Award rewards
        $reward = $this->awardTouristVisitRewards($user, $spot);

        return response()->json([
            'status' => 'success',
            'message' => "Visit recorded at {$spot->name}! 🌟",
            'spot_id' => $spot->id,
            'visits' => $spot->visits,
            'rewards' => $reward,
        ]);
    }

    /**
     * POST /api/tourist/location/ping
     * Update tourist's current GPS location and optionally auto-visit nearby spots.
     */
    public function locationPing(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;

        $user->update([
            'last_gps_lat' => $lat,
            'last_gps_lng' => $lng,
            'last_gps_ping_at' => now(),
        ]);

        // Check if tourist is currently inside any approved spot radius
        $nearbySpots = TouristSpot::where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->filter(function ($spot) use ($lat, $lng) {
                $d = $this->haversine($lat, $lng, (float) $spot->latitude, (float) $spot->longitude);
                return $d <= self::MAX_DISTANCE_METERS;
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Location updated.',
            'last_gps_lat' => $lat,
            'last_gps_lng' => $lng,
            'nearby_count' => $nearbySpots->count(),
            'nearby_spots' => $nearbySpots->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    /**
     * Haversine formula — distance in meters between two lat/lng points.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1);
    }
}
