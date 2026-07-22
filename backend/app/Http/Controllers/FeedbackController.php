<?php

namespace App\Http\Controllers;

use App\Models\FeedbackImage;
use App\Models\SiteFeedback;
use App\Models\TouristSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    /**
     * GET /api/tourist/feedback
     * Returns testimonies and policy recommendations, optionally filtered by tourist_spot_id.
     */
    public function index(Request $request): JsonResponse
    {
        $spotId = $request->query('tourist_spot_id');

        $query = SiteFeedback::with(['user:id,name,avatar', 'images:id,feedback_id,image_path'])
            ->latest();

        if ($spotId) {
            $query->where('tourist_spot_id', $spotId);
        }

        $feedbacks = $query->get();

        // Calculate summary metrics if spot ID is provided
        $summary = null;
        if ($spotId) {
            $spot = TouristSpot::find($spotId);

            // Calculate distributions
            $crowdDistribution = SiteFeedback::where('tourist_spot_id', $spotId)
                ->select('crowd_level', DB::raw('count(*) as count'))
                ->whereNotNull('crowd_level')
                ->groupBy('crowd_level')
                ->pluck('count', 'crowd_level');

            $cleanlinessDistribution = SiteFeedback::where('tourist_spot_id', $spotId)
                ->select('cleanliness_level', DB::raw('count(*) as count'))
                ->whereNotNull('cleanliness_level')
                ->groupBy('cleanliness_level')
                ->pluck('count', 'cleanliness_level');

            $safetyDistribution = SiteFeedback::where('tourist_spot_id', $spotId)
                ->select('safety_level', DB::raw('count(*) as count'))
                ->whereNotNull('safety_level')
                ->groupBy('safety_level')
                ->pluck('count', 'safety_level');

            $summary = [
                'average_rating' => $spot ? round($spot->rating, 1) : 0,
                'total_reviews' => SiteFeedback::where('tourist_spot_id', $spotId)->count(),
                'crowd' => [
                    'low' => (int)($crowdDistribution['low'] ?? 0),
                    'medium' => (int)($crowdDistribution['medium'] ?? 0),
                    'high' => (int)($crowdDistribution['high'] ?? 0),
                ],
                'cleanliness' => [
                    'clean' => (int)($cleanlinessDistribution['clean'] ?? 0),
                    'moderate' => (int)($cleanlinessDistribution['moderate'] ?? 0),
                    'dirty' => (int)($cleanlinessDistribution['dirty'] ?? 0),
                ],
                'safety' => [
                    'safe' => (int)($safetyDistribution['safe'] ?? 0),
                    'moderate' => (int)($safetyDistribution['moderate'] ?? 0),
                    'unsafe' => (int)($safetyDistribution['unsafe'] ?? 0),
                ]
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $feedbacks,
            'summary' => $summary
        ]);
    }

    /**
     * POST /api/tourist/feedback
     * Submit a testimony and/or policy recommendation with optional images.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tourist_spot_id' => 'nullable|integer|exists:tourist_spots,id',
            'rating' => 'nullable|integer|between:1,5',
            'testimony' => 'nullable|string',
            'policy_recommendation' => 'nullable|string',
            'crowd_level' => 'nullable|string|in:low,medium,high',
            'cleanliness_level' => 'nullable|string|in:clean,moderate,dirty',
            'safety_level' => 'nullable|string|in:safe,moderate,unsafe',
            'images.*' => 'nullable|image|max:10240', // Max 10MB per image
        ]);

        $user = $request->user();

        // Save feedback
        $feedback = SiteFeedback::create([
            'user_id' => $user ? $user->id : ($request->session()->get('user_id') ?? 1),
            'tourist_spot_id' => $request->tourist_spot_id,
            'rating' => $request->rating,
            'testimony' => $request->testimony,
            'policy_recommendation' => $request->policy_recommendation,
            'crowd_level' => $request->crowd_level,
            'cleanliness_level' => $request->cleanliness_level,
            'safety_level' => $request->safety_level,
        ]);

        // Handle Image Uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                if ($imageFile->isValid()) {
                    $filename = 'fb_' . time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
                    $destinationPath = storage_path('app/public/feedback_images');
                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0777, true);
                    }
                    if (!is_writable($destinationPath)) {
                        @chmod($destinationPath, 0777);
                        if (str_starts_with(PHP_OS, 'WIN')) {
                            @exec('attrib -r "' . $destinationPath . '" /d');
                        }
                    }
                    $imageFile->move($destinationPath, $filename);

                    FeedbackImage::create([
                        'feedback_id' => $feedback->id,
                        'image_path'  => '/storage/feedback_images/' . $filename,
                    ]);
                }
            }
        }

        // If specific tourist spot feedback is given with a rating, recalculate average rating
        if ($request->tourist_spot_id && $request->rating) {
            $spot = TouristSpot::find($request->tourist_spot_id);
            if ($spot) {
                $avgRating = SiteFeedback::where('tourist_spot_id', $spot->id)
                    ->whereNotNull('rating')
                    ->avg('rating');
                $spot->rating = round($avgRating, 2);
                $spot->save();

                \Illuminate\Support\Facades\Cache::forget('feedback_stats_province');
                \Illuminate\Support\Facades\Cache::forget('feedback_gallery_province');
                if ($spot->municipality_id) {
                    \Illuminate\Support\Facades\Cache::forget("feedback_stats_muni_{$spot->municipality_id}");
                    \Illuminate\Support\Facades\Cache::forget("feedback_gallery_muni_{$spot->municipality_id}");
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you for your testimony and feedback!',
            'data' => $feedback->load('images')
        ]);
    }
}
