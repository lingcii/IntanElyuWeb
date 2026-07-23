<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteFeedback;
use App\Models\TouristSpot;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FeedbackManagementController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Determine whether the current user is scoped to a specific municipality.
     * Returns the municipality_id (int) if municipal role, null if province-wide.
     */
    private function getScopedMunicipalityId(Request $request): ?int
    {
        $role = $request->session()->get('user_role');
        if ($role === 'pitco' || $role === 'picto' || $role === 'lupto') {
            return null; // province-wide
        }
        // All *_mto and 'municipal' roles are scoped
        $userId = $request->session()->get('user_id');
        if (!$userId) return null;
        $user = User::find($userId);
        return $user?->municipality_id;
    }

    /**
     * Base query: tourist_spots filtered by municipality if needed.
     */
    private function baseSpotQuery(?int $municipalityId)
    {
        $query = TouristSpot::query()->whereNotIn('status', ['draft', 'rejected', 'pending']);
        if ($municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }
        return $query;
    }

    /**
     * Base feedback query: joins through tourist_spots with optional municipality scope.
     */
    private function baseFeedbackQuery(?int $municipalityId)
    {
        $query = SiteFeedback::query()
            ->whereNotNull('site_feedbacks.rating')
            ->join('tourist_spots', 'site_feedbacks.tourist_spot_id', '=', 'tourist_spots.id')
            ->whereNotIn('tourist_spots.status', ['draft', 'rejected', 'pending']);
        if ($municipalityId) {
            $query->where('tourist_spots.municipality_id', $municipalityId);
        }
        return $query->select('site_feedbacks.*');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  1. Dashboard Statistics
    // ──────────────────────────────────────────────────────────────────────────

    public function dashboardStats(Request $request)
    {
        $municipalityId = $this->getScopedMunicipalityId($request);
        $cacheKey = 'feedback_stats_v2_' . ($municipalityId ?? 'province');

        $data = Cache::remember($cacheKey, 30, function () use ($municipalityId) {
            // Single aggregated query for total, avg, and star breakdown
            $aggregates = $this->baseFeedbackQuery($municipalityId)
                ->select(
                    DB::raw('COUNT(*) as total_feedback'),
                    DB::raw('AVG(site_feedbacks.rating) as avg_rating'),
                    DB::raw('SUM(CASE WHEN site_feedbacks.rating = 5 THEN 1 ELSE 0 END) as star_5'),
                    DB::raw('SUM(CASE WHEN site_feedbacks.rating = 4 THEN 1 ELSE 0 END) as star_4'),
                    DB::raw('SUM(CASE WHEN site_feedbacks.rating = 3 THEN 1 ELSE 0 END) as star_3'),
                    DB::raw('SUM(CASE WHEN site_feedbacks.rating = 2 THEN 1 ELSE 0 END) as star_2'),
                    DB::raw('SUM(CASE WHEN site_feedbacks.rating = 1 THEN 1 ELSE 0 END) as star_1')
                )
                ->first();

            $totalFeedback = (int) ($aggregates->total_feedback ?? 0);
            $avgRating     = $aggregates->avg_rating ? (float) $aggregates->avg_rating : 0;

            $ratingBreakdown = [
                5 => (int) ($aggregates->star_5 ?? 0),
                4 => (int) ($aggregates->star_4 ?? 0),
                3 => (int) ($aggregates->star_3 ?? 0),
                2 => (int) ($aggregates->star_2 ?? 0),
                1 => (int) ($aggregates->star_1 ?? 0),
            ];

            // Total spots with at least one review
            $spotsReviewed = $this->baseSpotQuery($municipalityId)
                ->whereHas('feedbacks', fn($q) => $q->whereNotNull('site_feedbacks.rating'))
                ->count();

            // Top 10 highest rated spots
            $topRatedSpots = TouristSpot::with(['municipality'])
                ->whereNotIn('status', ['draft', 'rejected', 'pending'])
                ->whereHas('feedbacks', fn($q) => $q->whereNotNull('site_feedbacks.rating'))
                ->withAvg(['feedbacks' => fn($q) => $q->whereNotNull('site_feedbacks.rating')], 'rating')
                ->withCount(['feedbacks' => fn($q) => $q->whereNotNull('site_feedbacks.rating')])
                ->when($municipalityId, fn($q) => $q->where('municipality_id', $municipalityId))
                ->orderByDesc('feedbacks_avg_rating')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn($s) => [
                    'id'             => $s->id,
                    'name'           => $s->name,
                    'municipality'   => $s->municipality?->name,
                    'category'       => $s->category,
                    'avg_rating'     => round((float) ($s->feedbacks_avg_rating ?? 0), 2),
                    'total_reviews'  => (int) $s->feedbacks_count,
                ]);

            // Most reviewed spots
            $mostReviewed = TouristSpot::with(['municipality'])
                ->whereNotIn('status', ['draft', 'rejected', 'pending'])
                ->whereHas('feedbacks', fn($q) => $q->whereNotNull('site_feedbacks.rating'))
                ->withCount(['feedbacks' => fn($q) => $q->whereNotNull('site_feedbacks.rating')])
                ->withAvg(['feedbacks' => fn($q) => $q->whereNotNull('site_feedbacks.rating')], 'rating')
                ->when($municipalityId, fn($q) => $q->where('municipality_id', $municipalityId))
                ->orderByDesc('feedbacks_count')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn($s) => [
                    'id'             => $s->id,
                    'name'           => $s->name,
                    'municipality'   => $s->municipality?->name,
                    'category'       => $s->category,
                    'avg_rating'     => round((float) ($s->feedbacks_avg_rating ?? 0), 2),
                    'total_reviews'  => (int) $s->feedbacks_count,
                ]);

            // Monthly trend (last 12 months)
            $monthlyTrend = $this->baseFeedbackQuery($municipalityId)
                ->select(
                    DB::raw('DATE_FORMAT(site_feedbacks.created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('ROUND(AVG(site_feedbacks.rating), 2) as avg_rating')
                )
                ->where('site_feedbacks.created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            return [
                'stats' => [
                    'spots_reviewed' => $spotsReviewed,
                    'total_feedback' => $totalFeedback,
                    'avg_rating'     => $avgRating ? round($avgRating, 2) : 0,
                    'five_star'      => $ratingBreakdown[5],
                    'four_star'      => $ratingBreakdown[4],
                    'three_star'     => $ratingBreakdown[3],
                    'two_star'       => $ratingBreakdown[2],
                    'one_star'       => $ratingBreakdown[1],
                ],
                'rating_breakdown'        => $ratingBreakdown,
                'municipality_comparison' => [],
                'top_rated_spots'         => $topRatedSpots,
                'most_reviewed_spots'     => $mostReviewed,
                'monthly_trend'           => $monthlyTrend,
            ];
        });

        return response()->json($data);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  2. Gallery View — Tourist Spots as Cards
    // ──────────────────────────────────────────────────────────────────────────

    public function gallery(Request $request)
    {
        $municipalityId = $this->getScopedMunicipalityId($request);

        $search      = trim($request->get('search', ''));
        $municipality = $request->get('municipality', '');
        $category    = $request->get('category', '');
        $minRating   = $request->get('min_rating', '');
        $sort        = $request->get('sort', 'newest');
        $perPage     = min((int) $request->get('per_page', 15), 60);

        $query = TouristSpot::with(['municipality', 'images' => fn($q) => $q->where('is_primary', true)->limit(1)])
            ->withAvg(['feedbacks' => fn($q) => $q->whereNotNull('site_feedbacks.rating')], 'rating')
            ->withCount(['feedbacks' => fn($q) => $q->whereNotNull('site_feedbacks.rating')])
            ->whereNotIn('tourist_spots.status', ['draft', 'rejected', 'pending']);

        // Municipality scoping (hard lock for municipal users)
        if ($municipalityId) {
            $query->where('municipality_id', $municipalityId);
        } elseif ($municipality) {
            if (is_numeric($municipality)) {
                $query->where('municipality_id', $municipality);
            } else {
                $query->whereHas('municipality', fn($q) => $q->where('name', 'like', "%{$municipality}%"));
            }
        }

        if ($search) {
            $query->where('tourist_spots.name', 'like', "%{$search}%");
        }

        if ($category) {
            $query->where('tourist_spots.category', $category);
        }

        // Sorting (DESC default order for newest added spots first)
        switch ($sort) {
            case 'highest_rated':
                $query->orderByDesc('feedbacks_avg_rating')->orderByDesc('tourist_spots.id');
                break;
            case 'lowest_rated':
                $query->orderBy('feedbacks_avg_rating')->orderByDesc('tourist_spots.id');
                break;
            case 'most_reviewed':
                $query->orderByDesc('feedbacks_count')->orderByDesc('tourist_spots.id');
                break;
            case 'alphabetical':
                $query->orderBy('tourist_spots.name');
                break;
            case 'oldest':
                $query->orderBy('tourist_spots.id');
                break;
            case 'newest':
            default:
                $query->orderByDesc('tourist_spots.id');
        }

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($spot) {
            $photoUrl = null;
            if ($spot->images->count()) {
                $photoUrl = $spot->images->first()->photo_url;
            } elseif ($spot->photo_url) {
                $photoUrl = $spot->photo_url;
            }

            return [
                'id'            => $spot->id,
                'name'          => $spot->name,
                'municipality'  => $spot->municipality?->name,
                'municipality_id' => $spot->municipality_id,
                'category'      => $spot->category,
                'photo_url'     => $photoUrl,
                'avg_rating'    => round((float) $spot->feedbacks_avg_rating, 2),
                'total_reviews' => (int) $spot->feedbacks_count,
            ];
        });

        // Load all municipalities for filter dropdown
        $municipalities = Cache::remember('feedback_meta_muni', 600, function () {
            return Municipality::orderBy('name')->select('id', 'name')->get();
        });

        // Load all categories for filter dropdown
        $categories = Cache::remember('feedback_meta_cat_all', 600, function () {
            return TouristSpot::query()
                ->whereNotIn('status', ['draft', 'rejected', 'pending'])
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');
        });

        return response()->json([
            'data'           => $items,
            'current_page'   => $paginated->currentPage(),
            'last_page'      => $paginated->lastPage(),
            'total'          => $paginated->total(),
            'municipalities' => $municipalities,
            'categories'     => $categories,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  3. Table View — All Feedback Rows
    // ──────────────────────────────────────────────────────────────────────────

    public function table(Request $request)
    {
        $municipalityId = $this->getScopedMunicipalityId($request);

        $search      = trim($request->get('search', ''));
        $municipality = $request->get('municipality', '');
        $category    = $request->get('category', '');
        $rating      = $request->get('rating', '');
        $dateFrom    = $request->get('date_from', '');
        $dateTo      = $request->get('date_to', '');
        $sort        = $request->get('sort', 'newest');
        $perPage     = min((int) $request->get('per_page', 20), 100);

        $query = SiteFeedback::with([
                'touristSpot.municipality',
                'user',
                'images',
            ])
            ->whereNotNull('site_feedbacks.rating')
            ->join('tourist_spots', 'site_feedbacks.tourist_spot_id', '=', 'tourist_spots.id')
            ->join('municipalities', 'tourist_spots.municipality_id', '=', 'municipalities.id')
            ->select('site_feedbacks.*');

        // Municipality scoping
        if ($municipalityId) {
            $query->where('tourist_spots.municipality_id', $municipalityId);
        } elseif ($municipality) {
            $query->where('tourist_spots.municipality_id', $municipality);
        }

        // Category filter
        if ($category) {
            $query->where('tourist_spots.category', $category);
        }

        // Rating filter
        if ($rating !== '') {
            $query->where('site_feedbacks.rating', (int) $rating);
        }

        // Date range filter
        if ($dateFrom) {
            $query->where('site_feedbacks.created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('site_feedbacks.created_at', '<=', $dateTo . ' 23:59:59');
        }

        // Search (spot name, municipality, user name, comment)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tourist_spots.name', 'like', "%{$search}%")
                  ->orWhere('municipalities.name', 'like', "%{$search}%")
                  ->orWhere('site_feedbacks.testimony', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        // Sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('site_feedbacks.created_at', 'asc');
                break;
            case 'highest_rated':
                $query->orderByDesc('site_feedbacks.rating');
                break;
            case 'lowest_rated':
                $query->orderBy('site_feedbacks.rating');
                break;
            default:
                $query->orderByDesc('site_feedbacks.created_at');
        }

        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(fn($fb) => [
            'id'             => $fb->id,
            'tourist_spot'   => $fb->touristSpot?->name,
            'municipality'   => $fb->touristSpot?->municipality?->name,
            'category'       => $fb->touristSpot?->category,
            'rating'         => $fb->rating,
            'comment'        => $fb->testimony,
            'crowd_level'    => $fb->crowd_level,
            'cleanliness'    => $fb->cleanliness_level,
            'safety'         => $fb->safety_level,
            'user_name'      => $fb->user?->name ?? 'Anonymous',
            'user_avatar'    => $fb->user?->avatar,
            'date'           => $fb->created_at?->format('M d, Y'),
            'date_raw'       => $fb->created_at?->toISOString(),
            'images'         => $fb->images->map(fn($img) => $img->image_path)->values(),
        ]);

        return response()->json([
            'data'         => $items,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  4. Spot Detail — Single Tourist Spot with All Reviews
    // ──────────────────────────────────────────────────────────────────────────

    public function spotDetails(Request $request, $id)
    {
        $municipalityId = $this->getScopedMunicipalityId($request);
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 10), 50);
        $sort    = $request->get('sort', 'newest');

        $cacheKey = "spot_details_{$id}_m" . ($municipalityId ?? '0') . "_p{$page}_pp{$perPage}_s{$sort}";

        return Cache::remember($cacheKey, 15, function () use ($request, $id, $municipalityId, $perPage, $sort) {
            $spot = TouristSpot::with(['municipality', 'images'])
                ->withAvg(['feedbacks' => fn($q) => $q->whereNotNull('rating')], 'rating')
                ->withCount(['feedbacks' => fn($q) => $q->whereNotNull('rating')])
                ->find($id);

            if (!$spot) {
                return response()->json(['error' => 'Tourist spot not found.'], 404);
            }

            // Security: municipal users can only access their own municipality's spots
            if ($municipalityId && $spot->municipality_id !== $municipalityId) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }

            // Rating breakdown
            $breakdown = SiteFeedback::where('tourist_spot_id', $id)
                ->whereNotNull('rating')
                ->select('rating', DB::raw('COUNT(*) as count'))
                ->groupBy('rating')
                ->pluck('count', 'rating');

            $ratingBreakdown = [];
            for ($i = 5; $i >= 1; $i--) {
                $ratingBreakdown[$i] = (int) ($breakdown[$i] ?? 0);
            }

            // Cover image
            $coverImage = $spot->images->where('is_primary', true)->first()?->photo_url
                ?? $spot->images->first()?->photo_url
                ?? $spot->photo_url;

            // All gallery images
            $galleryImages = $spot->images->map(fn($img) => $img->photo_url)->values();

            $reviewsQuery = SiteFeedback::with(['user', 'images'])
                ->where('tourist_spot_id', $id)
                ->whereNotNull('rating');

            switch ($sort) {
                case 'oldest':
                    $reviewsQuery->orderBy('created_at', 'asc');
                    break;
                case 'highest_rated':
                    $reviewsQuery->orderByDesc('rating');
                    break;
                case 'lowest_rated':
                    $reviewsQuery->orderBy('rating');
                    break;
                default:
                    $reviewsQuery->orderByDesc('created_at');
            }

            $paginated = $reviewsQuery->paginate($perPage);

            $reviews = collect($paginated->items())->map(fn($fb) => [
                'id'           => $fb->id,
                'rating'       => $fb->rating,
                'comment'      => $fb->testimony,
                'crowd_level'  => $fb->crowd_level,
                'cleanliness'  => $fb->cleanliness_level,
                'safety'       => $fb->safety_level,
                'user_name'    => $fb->user?->name ?? 'Anonymous',
                'user_avatar'  => $fb->user?->avatar,
                'date'         => $fb->created_at?->format('M d, Y'),
                'date_raw'     => $fb->created_at?->toISOString(),
                'images'       => $fb->images->map(fn($img) => $img->image_path)->values(),
            ]);

            return response()->json([
                'spot' => [
                    'id'            => $spot->id,
                    'name'          => $spot->name,
                    'municipality'  => $spot->municipality?->name,
                    'category'      => $spot->category,
                    'description'   => $spot->description,
                    'cover_image'   => $coverImage,
                    'gallery'       => $galleryImages,
                    'avg_rating'    => round((float) $spot->feedbacks_avg_rating, 2),
                    'total_reviews' => (int) $spot->feedbacks_count,
                ],
                'rating_breakdown' => $ratingBreakdown,
                'reviews'          => $reviews,
                'current_page'     => $paginated->currentPage(),
                'last_page'        => $paginated->lastPage(),
                'total_reviews'    => $paginated->total(),
            ]);
        });
    }
}
