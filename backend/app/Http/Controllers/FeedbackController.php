<?php

namespace App\Http\Controllers;

use App\Models\SiteFeedback;
use App\Models\FeedbackImage;
use App\Models\TouristSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    /**
     * GET /api/tourist/feedback
     * List feedback for a tourist spot (mobile app).
     */
    public function index(Request $request)
    {
        $spotId  = $request->get('tourist_spot_id');
        $perPage = min((int) $request->get('per_page', 10), 50);

        $query = SiteFeedback::with(['user', 'images'])
            ->whereNotNull('rating');

        if ($spotId) {
            $query->where('tourist_spot_id', $spotId);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        $items = collect($paginated->items())->map(fn($fb) => [
            'id'           => $fb->id,
            'rating'       => $fb->rating,
            'comment'      => $fb->testimony,
            'crowd_level'  => $fb->crowd_level,
            'cleanliness'  => $fb->cleanliness_level,
            'safety'       => $fb->safety_level,
            'user_name'    => $fb->user?->name ?? 'Anonymous',
            'user_avatar'  => $fb->user?->avatar,
            'date'         => $fb->created_at?->format('M d, Y'),
            'images'       => $fb->images->map(fn($img) => $img->image_path)->values(),
        ]);

        return response()->json([
            'data'         => $items,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
        ]);
    }

    /**
     * POST /api/tourist/feedback
     * Submit a new review from the mobile app.
     */
    public function store(Request $request)
    {
        $userId = $request->session()->get('user_id');
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'tourist_spot_id'       => 'required|exists:tourist_spots,id',
            'rating'                => 'required|integer|min:1|max:5',
            'testimony'             => 'nullable|string|max:2000',
            'policy_recommendation' => 'nullable|string|max:2000',
            'crowd_level'           => 'nullable|in:low,medium,high',
            'cleanliness_level'     => 'nullable|in:clean,moderate,dirty',
            'safety_level'          => 'nullable|in:safe,moderate,unsafe',
            'images'                => 'nullable|array|max:5',
            'images.*'              => 'nullable|image|max:5120',
        ]);

        $feedback = SiteFeedback::create([
            'user_id'               => $userId,
            'tourist_spot_id'       => $validated['tourist_spot_id'],
            'rating'                => $validated['rating'],
            'testimony'             => $validated['testimony'] ?? null,
            'policy_recommendation' => $validated['policy_recommendation'] ?? null,
            'crowd_level'           => $validated['crowd_level'] ?? null,
            'cleanliness_level'     => $validated['cleanliness_level'] ?? null,
            'safety_level'          => $validated['safety_level'] ?? null,
        ]);

        // Handle uploaded images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('feedback', 'public');
                FeedbackImage::create([
                    'feedback_id' => $feedback->id,
                    'image_path'  => $path,
                ]);
            }
        }

        // Update the spot's denormalized rating column
        $avgRating = SiteFeedback::where('tourist_spot_id', $validated['tourist_spot_id'])
            ->whereNotNull('rating')
            ->avg('rating');
        TouristSpot::where('id', $validated['tourist_spot_id'])
            ->update(['rating' => round($avgRating, 2)]);

        return response()->json([
            'message'  => 'Feedback submitted successfully.',
            'feedback' => $feedback->load(['images']),
        ], 201);
    }
}
