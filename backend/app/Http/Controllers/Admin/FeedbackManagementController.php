<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteFeedback;
use App\Models\TouristSpot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedbackManagementController extends Controller
{
    /**
     * Helper to get user role & scoped municipality ID from request session
     */
    private function getScope(Request $request): array
    {
        $role = $request->session()->get('user_role');
        $userMunicipalityId = (int) $request->session()->get('user_municipality_id', 0);
        $isMunicipal = in_array($role, User::$MUNICIPAL_ROLES);

        return [
            'role' => $role,
            'is_municipal' => $isMunicipal,
            'municipality_id' => $isMunicipal ? $userMunicipalityId : null,
        ];
    }

    /**
     * GET /api/{role}/feedback/dashboard-stats
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $scope = $this->getScope($request);
        $cacheKey = "feedback_stats_" . ($scope['is_municipal'] ? "muni_" . $scope['municipality_id'] : "province");

        $stats = Cache::remember($cacheKey, 120, function () use ($scope) {
            // Base query for site_feedbacks linked to a tourist spot
            $feedbackQuery = SiteFeedback::whereNotNull('tourist_spot_id');

            if ($scope['is_municipal'] && $scope['municipality_id']) {
                $feedbackQuery->whereHas('touristSpot', function ($q) use ($scope) {
                    $q->where('municipality_id', $scope['municipality_id']);
                });
            }

            // Base query for tourist spots
            $spotsQuery = TouristSpot::query();
            if ($scope['is_municipal'] && $scope['municipality_id']) {
                $spotsQuery->where('municipality_id', $scope['municipality_id']);
            }

            $totalReviewedSpots = (clone $feedbackQuery)->distinct('tourist_spot_id')->count('tourist_spot_id');
            $totalFeedback = (clone $feedbackQuery)->count();
            $avgRatingRaw = (clone $feedbackQuery)->whereNotNull('rating')->avg('rating');
            $avgRating = $avgRatingRaw ? round((float)$avgRatingRaw, 1) : 0.0;

            // Rating breakdown (5-star down to 1-star)
            $ratingCountsRaw = (clone $feedbackQuery)
                ->whereNotNull('rating')
                ->select('rating', DB::raw('count(*) as count'))
                ->groupBy('rating')
                ->pluck('count', 'rating')
                ->toArray();

            $ratingBreakdown = [
                5 => (int)($ratingCountsRaw[5] ?? 0),
                4 => (int)($ratingCountsRaw[4] ?? 0),
                3 => (int)($ratingCountsRaw[3] ?? 0),
                2 => (int)($ratingCountsRaw[2] ?? 0),
                1 => (int)($ratingCountsRaw[1] ?? 0),
            ];

            // Municipality Comparison (Only for province-wide users: PICTO / LUPTO)
            $municipalityComparison = [];
            if (!$scope['is_municipal']) {
                $municipalityComparison = DB::table('municipalities')
                    ->join('tourist_spots', 'municipalities.id', '=', 'tourist_spots.municipality_id')
                    ->join('site_feedbacks', 'tourist_spots.id', '=', 'site_feedbacks.tourist_spot_id')
                    ->select(
                        'municipalities.name as municipality',
                        DB::raw('ROUND(AVG(site_feedbacks.rating), 1) as avg_rating'),
                        DB::raw('COUNT(site_feedbacks.id) as total_reviews')
                    )
                    ->whereNotNull('site_feedbacks.rating')
                    ->groupBy('municipalities.id', 'municipalities.name')
                    ->orderByDesc('avg_rating')
                    ->get()
                    ->toArray();
            }

            // Top 10 Highest Rated Spots
            $topSpotsQuery = (clone $spotsQuery)
                ->with(['municipality:id,name'])
                ->withCount(['feedbacks as total_reviews' => function ($q) {
                    $q->whereNotNull('rating');
                }])
                ->withAvg(['feedbacks as avg_rating' => function ($q) {
                    $q->whereNotNull('rating');
                }], 'rating')
                ->having('total_reviews', '>', 0)
                ->orderByDesc('avg_rating')
                ->orderByDesc('total_reviews')
                ->limit(10)
                ->get()
                ->map(function ($spot) {
                    return [
                        'id' => $spot->id,
                        'name' => $spot->name,
                        'municipality' => $spot->municipality ? $spot->municipality->name : 'N/A',
                        'avg_rating' => round((float)($spot->avg_rating ?? $spot->rating ?? 0), 1),
                        'total_reviews' => (int)$spot->total_reviews,
                    ];
                })
                ->values()
                ->all();

            // Most Reviewed Spots
            $mostReviewedSpotsQuery = (clone $spotsQuery)
                ->with(['municipality:id,name'])
                ->withCount(['feedbacks as total_reviews' => function ($q) {
                    $q->whereNotNull('rating');
                }])
                ->withAvg(['feedbacks as avg_rating' => function ($q) {
                    $q->whereNotNull('rating');
                }], 'rating')
                ->having('total_reviews', '>', 0)
                ->orderByDesc('total_reviews')
                ->orderByDesc('avg_rating')
                ->limit(10)
                ->get()
                ->map(function ($spot) {
                    return [
                        'id' => $spot->id,
                        'name' => $spot->name,
                        'municipality' => $spot->municipality ? $spot->municipality->name : 'N/A',
                        'avg_rating' => round((float)($spot->avg_rating ?? $spot->rating ?? 0), 1),
                        'total_reviews' => (int)$spot->total_reviews,
                    ];
                })
                ->values()
                ->all();

            // Monthly Feedback Trend (Last 12 Months)
            $monthlyTrend = (clone $feedbackQuery)
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month_key"),
                    DB::raw("DATE_FORMAT(created_at, '%b %Y') as label"),
                    DB::raw("COUNT(*) as total")
                )
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->groupBy('month_key', 'label')
                ->orderBy('month_key', 'asc')
                ->get()
                ->toArray();

            return [
                'total_reviewed_spots' => $totalReviewedSpots,
                'total_feedback'       => $totalFeedback,
                'average_rating'       => $avgRating,
                'rating_breakdown'     => $ratingBreakdown,
                'municipality_comparison' => $municipalityComparison,
                'top_rated_spots'      => $topSpotsQuery,
                'most_reviewed_spots'  => $mostReviewedSpotsQuery,
                'monthly_trend'        => $monthlyTrend,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $stats
        ]);
    }

    /**
     * GET /api/{role}/feedback/gallery
     * Retrieves tourist spots styled as gallery cards with rating summary & total reviews count
     */
    public function gallery(Request $request): JsonResponse
    {
        $scope = $this->getScope($request);

        $hasFilters = $request->filled('municipality_id') || $request->filled('category') || $request->filled('search') || $request->filled('min_rating') || $request->filled('sort');
        $page = (int)$request->input('page', 1);

        if (!$hasFilters && $page === 1) {
            $cacheKey = "feedback_gallery_" . ($scope['is_municipal'] ? "muni_" . $scope['municipality_id'] : "province");
            $cached = Cache::remember($cacheKey, 60, function () use ($request, $scope) {
                return $this->fetchGalleryData($request, $scope);
            });
            return response()->json($cached);
        }

        return response()->json($this->fetchGalleryData($request, $scope));
    }

    private function fetchGalleryData(Request $request, array $scope): array
    {
        $query = TouristSpot::select(['id', 'name', 'municipality_id', 'category', 'description', 'photo_url', 'rating'])
            ->with(['municipality:id,name', 'images:id,spot_id,photo_url,is_primary'])
            ->withCount(['feedbacks as total_reviews' => function ($q) {
                $q->whereNotNull('rating');
            }])
            ->withAvg(['feedbacks as avg_rating' => function ($q) {
                $q->whereNotNull('rating');
            }], 'rating');

        // Scope to municipality if user is municipal
        if ($scope['is_municipal'] && $scope['municipality_id']) {
            $query->where('municipality_id', $scope['municipality_id']);
        }

        // Filter: Municipality ID
        if ($request->filled('municipality_id') && !$scope['is_municipal']) {
            $query->where('municipality_id', $request->input('municipality_id'));
        }

        // Filter: Category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter: Search Keyword
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('category', 'like', $search)
                  ->orWhereHas('municipality', function ($mq) use ($search) {
                      $mq->where('name', 'like', $search);
                  });
            });
        }

        // Filter: Minimum Rating
        if ($request->filled('min_rating')) {
            $minRating = (float)$request->input('min_rating');
            $query->having(DB::raw('COALESCE(avg_rating, rating)'), '>=', $minRating);
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'highest_rated':
                $query->orderByDesc(DB::raw('COALESCE(avg_rating, rating)'));
                break;
            case 'lowest_rated':
                $query->orderBy(DB::raw('COALESCE(avg_rating, rating)'));
                break;
            case 'most_reviewed':
                $query->orderByDesc('total_reviews');
                break;
            case 'alphabetical':
                $query->orderBy('name', 'asc');
                break;
            case 'oldest':
                $query->orderBy('id', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $perPage = (int)$request->input('per_page', 12);
        $spots = $query->paginate($perPage);

        // Normalize spots data
        $items = collect($spots->items())->map(function ($spot) {
            $primaryImage = $spot->images->firstWhere('is_primary', true) ?? $spot->images->first();
            $photoUrl = $primaryImage ? $primaryImage->photo_url : ($spot->photo_url ?? null);

            return [
                'id'            => $spot->id,
                'name'          => $spot->name,
                'municipality'  => $spot->municipality ? $spot->municipality->name : 'N/A',
                'category'      => $spot->category,
                'photo_url'     => $photoUrl,
                'description'   => $spot->description,
                'average_rating'=> round((float)($spot->avg_rating ?? $spot->rating ?? 0), 1),
                'total_reviews' => (int)$spot->total_reviews,
            ];
        });

        return [
            'status' => 'success',
            'data'   => $items,
            'pagination' => [
                'current_page' => $spots->currentPage(),
                'last_page'    => $spots->lastPage(),
                'per_page'     => $spots->perPage(),
                'total'        => $spots->total(),
            ]
        ];
    }

    /**
     * GET /api/{role}/feedback/table
     * Returns tourist spots table view records with latest feedback summary, search, filter, and pagination
     */
    public function table(Request $request): JsonResponse
    {
        $scope = $this->getScope($request);

        $query = TouristSpot::select(['id', 'name', 'municipality_id', 'category', 'description', 'photo_url', 'rating', 'created_at'])
            ->with([
                'municipality:id,name',
                'feedbacks' => function ($q) {
                    $q->latest()->with('user:id,name,avatar');
                }
            ])
            ->withCount(['feedbacks as total_reviews' => function ($q) {
                $q->whereNotNull('rating');
            }])
            ->withAvg(['feedbacks as avg_rating' => function ($q) {
                $q->whereNotNull('rating');
            }], 'rating');

        // Scope to municipality if user is municipal
        if ($scope['is_municipal'] && $scope['municipality_id']) {
            $query->where('municipality_id', $scope['municipality_id']);
        }

        // Filter: Municipality ID
        if ($request->filled('municipality_id') && !$scope['is_municipal']) {
            $query->where('municipality_id', $request->input('municipality_id'));
        }

        // Filter: Category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter: Search Keyword
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('category', 'like', $search)
                  ->orWhereHas('municipality', function ($mq) use ($search) {
                      $mq->where('name', 'like', $search);
                  })
                  ->orWhereHas('feedbacks', function ($fq) use ($search) {
                      $fq->where('testimony', 'like', $search)
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', $search);
                        });
                  });
            });
        }

        // Filter: Rating Star
        if ($request->filled('rating')) {
            $minRating = (float)$request->input('rating');
            $query->having(DB::raw('COALESCE(avg_rating, rating)'), '>=', $minRating);
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'highest_rated':
                $query->orderByDesc(DB::raw('COALESCE(avg_rating, rating)'));
                break;
            case 'lowest_rated':
                $query->orderBy(DB::raw('COALESCE(avg_rating, rating)'));
                break;
            case 'most_reviewed':
                $query->orderByDesc('total_reviews');
                break;
            case 'alphabetical':
                $query->orderBy('name', 'asc');
                break;
            case 'oldest':
                $query->orderBy('id', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $perPage = (int)$request->input('per_page', 15);
        $spots = $query->paginate($perPage);

        $items = collect($spots->items())->map(function ($spot) {
            $latest = $spot->feedbacks->first();
            $user = $latest ? $latest->user : null;

            $commentText = $latest ? ($latest->testimony ?: ($latest->policy_recommendation ?: 'Rating submitted')) : 'No written reviews yet';
            $dateStr = $latest && $latest->created_at ? $latest->created_at->format('M j, Y') : ($spot->created_at ? $spot->created_at->format('M j, Y') : 'N/A');

            return [
                'id'             => $spot->id,
                'name'           => $spot->name,
                'municipality'   => $spot->municipality ? $spot->municipality->name : 'N/A',
                'category'       => $spot->category,
                'average_rating' => round((float)($spot->avg_rating ?? $spot->rating ?? 0), 1),
                'total_reviews'  => (int)$spot->total_reviews,
                'latest_feedback'=> [
                    'comment'     => $commentText,
                    'user_name'   => $user ? $user->name : ($latest ? 'Anonymous Tourist' : 'N/A'),
                    'user_avatar' => $user ? $user->avatar : null,
                    'date'        => $dateStr,
                ]
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'pagination' => [
                'current_page' => $spots->currentPage(),
                'last_page'    => $spots->lastPage(),
                'per_page'     => $spots->perPage(),
                'total'        => $spots->total(),
            ]
        ]);
    }

    /**
     * GET /api/{role}/feedback/spot-details/{id}
     * Retrieves tourist spot information, rating summary breakdown, and paginated user reviews
     */
    public function spotDetails(Request $request, int $id): JsonResponse
    {
        $scope = $this->getScope($request);

        $spot = TouristSpot::with(['municipality:id,name', 'images:id,spot_id,photo_url,is_primary'])->find($id);

        if (!$spot) {
            return response()->json(['error' => 'Tourist spot not found'], 404);
        }

        // Authorize municipal access restriction
        if ($scope['is_municipal'] && $scope['municipality_id'] && (int)$spot->municipality_id !== $scope['municipality_id']) {
            return response()->json(['error' => 'Forbidden: You cannot view feedback for tourist spots outside your municipality'], 403);
        }

        // Ratings breakdown for this spot
        $ratingCountsRaw = SiteFeedback::where('tourist_spot_id', $id)
            ->whereNotNull('rating')
            ->select('rating', DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $totalReviews = SiteFeedback::where('tourist_spot_id', $id)->count();
        $avgRatingRaw = SiteFeedback::where('tourist_spot_id', $id)->whereNotNull('rating')->avg('rating');
        $avgRating = $avgRatingRaw ? round((float)$avgRatingRaw, 1) : round((float)$spot->rating, 1);

        $ratingBreakdown = [
            5 => (int)($ratingCountsRaw[5] ?? 0),
            4 => (int)($ratingCountsRaw[4] ?? 0),
            3 => (int)($ratingCountsRaw[3] ?? 0),
            2 => (int)($ratingCountsRaw[2] ?? 0),
            1 => (int)($ratingCountsRaw[1] ?? 0),
        ];

        // Paginated reviews
        $reviews = SiteFeedback::with(['user:id,name,avatar', 'images:id,feedback_id,image_path'])
            ->where('tourist_spot_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate((int)$request->input('per_page', 10));

        $primaryImage = $spot->images->firstWhere('is_primary', true) ?? $spot->images->first();
        $photoUrl = $primaryImage ? $primaryImage->photo_url : ($spot->photo_url ?? null);

        $imagesList = $spot->images->map(function ($img) {
            return [
                'id' => $img->id,
                'photo_url' => $img->photo_url,
                'is_primary' => (bool)$img->is_primary,
            ];
        })->values()->all();

        if (empty($imagesList) && $photoUrl) {
            $imagesList = [[
                'id' => 0,
                'photo_url' => $photoUrl,
                'is_primary' => true,
            ]];
        }

        return response()->json([
            'status' => 'success',
            'spot' => [
                'id'             => $spot->id,
                'name'           => $spot->name,
                'municipality'   => $spot->municipality ? $spot->municipality->name : 'N/A',
                'category'       => $spot->category,
                'classification' => $spot->classification ?? 'Existing Destination',
                'description'    => $spot->description,
                'photo_url'      => $photoUrl,
                'images'         => $imagesList,
                'average_rating' => $avgRating,
                'total_reviews'  => $totalReviews,
                'rating_breakdown' => $ratingBreakdown,
            ],
            'reviews' => $reviews->items(),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ]
        ]);
    }
}
