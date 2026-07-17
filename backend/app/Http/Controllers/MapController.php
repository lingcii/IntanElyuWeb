<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\TouristSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * GET /api/lupto/map
     * Returns all municipalities with coordinates for LUPTO dashboard map.
     */
    public function luptoMapData(): JsonResponse
    {
        $municipalities = \Illuminate\Support\Facades\Cache::remember('map:lupto:municipalities', 3600, function () {
            return Municipality::select('id', 'name', 'latitude', 'longitude', 'attraction_count')
                ->orderBy('name')
                ->get();
        });

        return response()->json(['municipalities' => $municipalities]);
    }

    /**
     * GET /api/municipal/map
     * Returns municipality info + tourist spots for the user's municipality.
     */
    public function municipalityData(Request $request): JsonResponse
    {
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);
        $cacheKey = "map:muni:{$municipalityId}";

        $payload = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($municipalityId) {
            $municipality = Municipality::find($municipalityId);
            $spots        = TouristSpot::where('municipality_id', $municipalityId)
                ->with('municipality:id,name')
                ->latest()
                ->get();

            return ['municipality' => $municipality, 'spots' => $spots];
        });

        return response()->json($payload);
    }

    /**
     * GET /api/public/map
     * Returns all approved tourist spots for the mobile map view (no auth required).
     */
    public function publicMapData(): JsonResponse
    {
        $spots = \Illuminate\Support\Facades\Cache::remember('map:public:spots', 300, function () {
            return TouristSpot::where('status', 'approved')
                ->with('municipality:id,name')
                ->get(['id', 'name', 'category', 'municipality_id', 'barangay', 'latitude', 'longitude',
                       'entrance_fee', 'photo_url', 'description', 'opening_time', 'closing_time',
                       'is_maintenance', 'rating', 'visits', 'classification_status'])
                ->map(function ($spot) {
                    $imageUrl = null;
                    if ($spot->photo_url) {
                        $imageUrl = str_starts_with($spot->photo_url, 'http')
                            ? $spot->photo_url
                            : rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/storage/' . $spot->photo_url;
                    }
                    return [
                        'id'                    => $spot->id,
                        'name'                  => $spot->name,
                        'category'              => $spot->category,
                        'municipality'          => $spot->municipality?->name,
                        'location'              => $spot->barangay,
                        'lat'                   => $spot->latitude,
                        'lng'                   => $spot->longitude,
                        'entrance_fee'          => $spot->entrance_fee,
                        'image'                 => $imageUrl,
                        'description'           => $spot->description,
                        'opening_time'          => $spot->opening_time,
                        'closing_time'          => $spot->closing_time,
                        'is_maintenance'        => $spot->is_maintenance,
                        'rating'                => $spot->rating,
                        'visits'                => $spot->visits,
                        'classification_status' => $spot->classification_status,
                    ];
                })->values()->toArray();  // toArray() stores a plain array in cache — safe to serialize
        });

        return response()->json(['destinations' => $spots]);
    }
}
