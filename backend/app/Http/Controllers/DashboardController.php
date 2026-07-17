<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Analytics;
use App\Models\Municipality;
use App\Models\SystemStatus;
use App\Models\TouristSpot;
use App\Models\User;
use App\Enums\ActivityAction;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\CacheInvalidationService;

class DashboardController extends Controller
{
    /**
     * Generate a cheap fingerprint hash for dashboard change detection.
     * Only queries aggregated counts — no large result sets.
     */
    private function dashboardFingerprint(bool $isMuni, int $municipalityId): string
    {
        $query = DB::table('tourist_spots');
        if ($isMuni && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }
        $counts = $query->selectRaw(
            "COUNT(*) as total, "
            . "COALESCE(SUM(status='approved'),0) as approved, "
            . "COALESCE(SUM(status='pending'),0) as pending, "
            . "COALESCE(SUM(status='rejected'),0) as rejected"
        )->first();

        $fareCount = DB::table('fare_guides')->count();
        $alertCount = DB::table('alerts')->where('is_read', false)->count();
        $activityCount = DB::table('activity_logs')->count();

        return md5(json_encode([$counts, $fareCount, $alertCount, $activityCount]));
    }

    /**
     * GET /api/{role}/dashboard/poll
     * Returns a cheap fingerprint hash for polling — no heavy data loading.
     * Frontend compares this to its last known hash and only does a full
     * refresh when the hash changes.
     */
    public function poll(Request $request): JsonResponse
    {
        $role           = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);
        $isMuni         = in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId;

        $hash = $this->dashboardFingerprint($isMuni, $municipalityId);

        return response()->json(['hash' => $hash, 'ts' => now()->timestamp]);
    }

    /**
     * GET /api/{role}/dashboard
     * Returns KPIs, municipality map pins, approved spots, system status, alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $role           = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);
        $isMuni         = in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId;

        $cacheKey = "dashboard:data:{$role}:{$municipalityId}:v3";
        $cacheTtl = $isMuni ? 120 : 120;

        $payload = Cache::remember($cacheKey, $cacheTtl, function () use ($isMuni, $municipalityId) {
            // 1. Tourist Spots counts
            $spotCountsQuery = DB::table('tourist_spots');
            if ($isMuni) {
                $spotCountsQuery->where('municipality_id', $municipalityId);
            }
            $spotCounts = $spotCountsQuery
                ->selectRaw("COUNT(*) as total, COALESCE(SUM(status='approved'), 0) as approved, COALESCE(SUM(status='pending'), 0) as pending")
                ->first();
            $totalSpots = (int) ($spotCounts->total ?? 0);
            $approvedSpots = (int) ($spotCounts->approved ?? 0);
            $pendingSpots = (int) ($spotCounts->pending ?? 0);

            // 2. Approved tourist spots list (scoped columns)
            $spotsQuery = TouristSpot::where('status', 'approved')
                ->with(['municipality:id,name', 'approver:id,name'])
                ->select('id', 'name', 'municipality_id', 'barangay', 'category', 'entrance_fee', 'description', 'photo_url', 'latitude', 'longitude', 'classification_status', 'status', 'rating', 'visits', 'opening_time', 'closing_time', 'is_maintenance', 'points', 'approved_by', 'approved_at', 'created_at');
            if ($isMuni) {
                $spotsQuery->where('municipality_id', $municipalityId);
            }
            $approvedSpotsList = $spotsQuery->get();

            // 3. User counts (consolidated into a single query)
            $userStats = User::selectRaw("
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN role = 'tourist' THEN 1 ELSE 0 END) as tourist_users
            ")->first();
            $activeUsers = (int) ($userStats->active_users ?? 0);
            $touristUsers = (int) ($userStats->tourist_users ?? 0);

            // 4. System Statuses
            $systemStatuses = SystemStatus::select('id', 'service_name', 'status', 'uptime', 'last_checked')->get();

            // Uptime calculation in PHP
            $uptimes = $systemStatuses->pluck('uptime')->toArray();
            if (!empty($uptimes)) {
                $avg = array_sum(array_map(fn($u) => (float) str_replace('%', '', $u), $uptimes)) / count($uptimes);
                $uptimeVal = number_format($avg, 2) . '%';
            } else {
                $uptimeVal = '99.95%';
            }

            // 5. Analytics current year data (trends and monthly visits consolidated)
            $currentYear = now()->year;
            $analyticsQuery = Analytics::where('year', $currentYear);
            if ($isMuni) {
                $analyticsQuery->where('municipality_id', $municipalityId);
            }
            $yearAnalytics = $analyticsQuery->get(['month', 'visits', 'municipality_id']);

            // Calculate monthly trends in PHP
            $visitorTrends = [];
            $groupedMonths = $yearAnalytics->groupBy('month');
            foreach ($groupedMonths as $month => $records) {
                $visitorTrends[] = [
                    'month' => (int) $month,
                    'visits' => (int) $records->sum('visits')
                ];
            }
            usort($visitorTrends, fn($a, $b) => $a['month'] <=> $b['month']);

            // Calculate current month visits in PHP (get real data by summing tourist_spots visits)
            $spotsVisitsQuery = TouristSpot::where('status', 'approved');
            if ($isMuni) {
                $spotsVisitsQuery->where('municipality_id', $municipalityId);
            }
            $monthlyVisits = (int) $spotsVisitsQuery->sum('visits');

            // 6. User points total
            $totalPoints = (int) DB::table('user_points')->sum('total_points');

            $kpis = [
                'total_municipalities'  => $isMuni ? 1 : Municipality::count(),
                'total_tourist_spots'   => $totalSpots,
                'total_approved_spots'  => $approvedSpots,
                'total_pending_spots'   => $pendingSpots,
                'total_visits'          => $monthlyVisits,
                'totalTouristSpots'     => $totalSpots,
                'activeUsers'           => $activeUsers,
                'monthlyVisits'         => $monthlyVisits,
                'systemUptime'          => $uptimeVal,
                'total_tourist_users'   => $touristUsers,
                'total_points_earned'   => $totalPoints,
                'total_fare_matrix'     => \App\Models\FareGuide::count(),
            ];

            // Municipalities
            if ($isMuni) {
                $rawMunis = Municipality::where('id', $municipalityId)->get();
            } else {
                $rawMunis = Municipality::orderBy('name')->get();
            }

            // Compute category counts per municipality in PHP
            $catRows = collect();
            $groupedByMuni = $approvedSpotsList->groupBy('municipality_id');
            foreach ($groupedByMuni as $muniId => $muniSpots) {
                $muniCats = collect();
                $groupedByCat = $muniSpots->groupBy('category');
                foreach ($groupedByCat as $category => $spots) {
                    $muniCats->push((object)[
                        'municipality_id' => $muniId,
                        'category' => $category,
                        'count' => $spots->count()
                    ]);
                }
                $catRows->put($muniId, $muniCats);
            }

            $municipalities = $rawMunis->map(function ($m) use ($catRows) {
                $m->categories = $catRows->get($m->id, collect())->values();
                return $m;
            });

            // Recent Alerts and Activities (only query here if NOT municipal)
            $recentAlerts = [];
            $recentActivities = [];
            if (!$isMuni) {
                $recentAlerts    = Alert::where('is_read', false)->latest()->take(5)->get(['id', 'message', 'type', 'created_at'])->toArray();
                $recentActivities = ActivityLog::with('user:id,name,email,role,avatar,municipality_id')->latest()->take(4)->get()->toArray();
            }

            // Category Distribution in PHP
            $catDist = [];
            $groupedCats = $approvedSpotsList->groupBy('category');
            foreach ($groupedCats as $category => $items) {
                $catDist[] = [
                    'category' => $category,
                    'cnt' => $items->count()
                ];
            }
            usort($catDist, fn($a, $b) => $b['cnt'] <=> $a['cnt']);

            // Top 5 Municipalities (province-wide only)
            $topMunis = [];
            if (!$isMuni) {
                $topMunis = Municipality::leftJoin('tourist_spots as ts', 'ts.municipality_id', '=', 'municipalities.id')
                    ->selectRaw('municipalities.id, municipalities.name, COALESCE(SUM(ts.visits), 0) as total_visits')
                    ->groupBy('municipalities.id', 'municipalities.name')
                    ->orderByDesc('total_visits')
                    ->limit(5)
                    ->get()
                    ->toArray();
            }

            // Top 5 Spots by Visits in PHP
            $topSpots = $approvedSpotsList->sortByDesc('visits')
                ->take(5)
                ->map(fn($spot) => [
                    'id' => $spot->id,
                    'name' => $spot->name,
                    'visits' => $spot->visits
                ])
                ->values()
                ->toArray();

            return [
                'kpis'                 => $kpis,
                'municipalities'       => $municipalities->toArray(),
                'touristSpots'         => $approvedSpotsList->toArray(),
                'systemStatuses'       => $systemStatuses->toArray(),
                'alerts'               => $recentAlerts,
                'recent_activities'    => $recentActivities,
                'visitorTrends'        => $visitorTrends,
                'categoryDistribution' => $catDist,
                'topMunicipalities'    => $topMunis,
                'topSpots'             => $topSpots,
            ];
        });

        // For municipal dashboard, fetch recent alerts and activities in real-time
        if ($isMuni) {
            $recentAlerts    = Alert::where('is_read', false)->latest()->take(5)->get(['id', 'message', 'type', 'created_at']);
            $recentActivities = ActivityLog::with('user:id,name,email,role,avatar,municipality_id')->latest()->take(4)->get();
            $payload['alerts'] = $recentAlerts->toArray();
            $payload['recent_activities'] = $recentActivities->toArray();
        }

        return $this->etagResponse($request, $payload);
    }

    /**
     * GET /api/{role}/dashboard/pending-spots
     * Pending tourist spots for approval (LUPTO).
     */
    public function pendingSpots(): JsonResponse
    {
        $spots = TouristSpot::where('status', 'pending')
            ->with('municipality:id,name')
            ->latest()
            ->get();

        return response()->json(['spots' => $spots]);
    }

    /**
     * POST /api/{role}/dashboard/approve-spot
     */
    public function approveSpot(Request $request): JsonResponse
    {
        // Only LUPTO and PICTO roles may approve spots
        $role = $request->session()->get('user_role');
        if (!in_array($role, ['lupto', 'picto'])) {
            return response()->json(['error' => 'Unauthorized. Only LUPTO and PICTO can approve spots.'], 403);
        }

        $request->validate(['id' => 'required|integer']);
        $spot = TouristSpot::findOrFail($request->id);

        $approverId = (int) $request->session()->get('user_id');
        $approvedAt = now();

        DB::transaction(function () use ($spot, $request, $approverId, $approvedAt) {
            $spot->update([
                'status'      => 'approved',
                'approved_by' => $approverId,
                'approved_at' => $approvedAt,
            ]);
            Municipality::where('id', $spot->municipality_id)
                ->increment('attraction_count');

            ActivityLogService::log(
                ActivityAction::SPOT_APPROVED,
                'Approval Management',
                'Tourist spot "' . $spot->name . '" approved',
                ['status' => 'pending'],
                ['status' => 'approved'],
                $request
            );

            $muniName = Municipality::find($spot->municipality_id)?->name;
            NotificationService::notifyMunicipality(
                $spot->municipality_id,
                'spot_approved',
                'Tourist Spot Approved',
                "\"{$spot->name}\" has been approved",
                [
                    'module'            => 'Tourist Spots',
                    'action_url'        => 'tourist-spots.php',
                    'spot_name'         => $spot->name,
                    'municipality_name' => $muniName,
                    'actor_name'        => $request->session()->get('user_name'),
                ]
            );

            NotificationService::notifyProvincial(
                'spot_approved',
                'Tourist Spot Approved',
                "\"{$spot->name}\" has been approved",
                [
                    'module'            => 'Tourist Spots',
                    'action_url'        => 'tourist-spots.php',
                    'spot_name'         => $spot->name,
                    'municipality_name' => $muniName,
                    'actor_name'        => $request->session()->get('user_name'),
                ]
            );
        });

        // Invalidate only the dashboard caches that changed — never flush everything
        CacheInvalidationService::invalidateDashboard($spot->municipality_id);
        CacheInvalidationService::invalidateTouristSpots($spot->municipality_id);
        CacheInvalidationService::invalidateLeaderboard();

        return response()->json([
            'success'     => true,
            'message'     => 'Tourist spot approved.',
            'approved_by' => $approverId,
            'approved_at' => $approvedAt->toISOString(),
        ]);
    }

    /**
     * POST /api/{role}/dashboard/reject-spot
     */
    public function rejectSpot(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);
        $spot = TouristSpot::findOrFail($request->id);
        $rejectionReason = $request->input('rejection_reason');
        $spot->update([
            'status' => 'rejected',
            'rejection_reason' => $rejectionReason,
        ]);

        ActivityLogService::log(
            ActivityAction::SPOT_REJECTED,
            'Approval Management',
            'Tourist spot "' . $spot->name . '" rejected' . ($rejectionReason ? ': ' . $rejectionReason : ''),
            ['status' => 'pending'],
            ['status' => 'rejected', 'rejection_reason' => $rejectionReason],
            $request
        );

        $muniName = Municipality::find($spot->municipality_id)?->name;
        NotificationService::notifyMunicipality(
            $spot->municipality_id,
            'spot_rejected',
            'Tourist Spot Rejected',
            "\"{$spot->name}\" was rejected" . ($rejectionReason ? ": {$rejectionReason}" : ''),
            [
                'module'            => 'Tourist Spots',
                'action_url'        => 'tourist-spots.php',
                'spot_name'         => $spot->name,
                'municipality_name' => $muniName,
                'actor_name'        => $request->session()->get('user_name'),
            ]
        );

        NotificationService::notifyProvincial(
            'spot_rejected',
            'Tourist Spot Rejected',
            "\"{$spot->name}\" was rejected" . ($rejectionReason ? ": {$rejectionReason}" : ''),
            [
                'module'            => 'Tourist Spots',
                'action_url'        => 'tourist-spots.php',
                'spot_name'         => $spot->name,
                'municipality_name' => $muniName,
                'actor_name'        => $request->session()->get('user_name'),
            ]
        );

        // Invalidate only the dashboard caches that changed
        CacheInvalidationService::invalidateDashboard($spot->municipality_id);
        CacheInvalidationService::invalidateLeaderboard();

        return response()->json(['success' => true, 'message' => 'Tourist spot rejected.']);
    }

    /**
     * POST /api/{role}/dashboard/batch-approve-spots
     * Fixed: was N+1 (one SELECT + one UPDATE per spot).
     * Now: one UPDATE IN (...) + grouped municipality increments.
     */
    public function batchApproveSpots(Request $request): JsonResponse
    {
        // Only LUPTO and PICTO roles may batch approve spots
        $role = $request->session()->get('user_role');
        if (!in_array($role, ['lupto', 'picto'])) {
            return response()->json(['error' => 'Unauthorized. Only LUPTO and PICTO can approve spots.'], 403);
        }

        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $ids = $request->ids;
        $approverId = (int) $request->session()->get('user_id');
        $approvedAt = now();

        DB::transaction(function () use ($ids, $request, $approverId, $approvedAt) {
            // Fetch only pending spots in one query
            $spots = TouristSpot::whereIn('id', $ids)
                ->where('status', 'pending')
                ->get(['id', 'name', 'municipality_id']);

            if ($spots->isEmpty()) return;

            // Single UPDATE for all matching spots
            TouristSpot::whereIn('id', $spots->pluck('id'))
                ->update([
                    'status'      => 'approved',
                    'approved_by' => $approverId,
                    'approved_at' => $approvedAt,
                ]);

            // Group by municipality and increment attraction_count once per municipality
            $countByMunicipality = $spots->groupBy('municipality_id')->map->count();
            foreach ($countByMunicipality as $municipalityId => $count) {
                Municipality::where('id', $municipalityId)->increment('attraction_count', $count);
            }

            foreach ($spots as $spot) {
                ActivityLogService::log(
                    ActivityAction::SPOT_APPROVED,
                    'Approval Management',
                    'Tourist spot "' . $spot->name . '" approved via batch approval',
                    ['status' => 'pending'],
                    ['status' => 'approved', 'approved_by' => $approverId],
                    $request
                );
            }
        });

        // Invalidate only the dashboard caches — never flush all caches
        CacheInvalidationService::invalidateDashboard();
        CacheInvalidationService::invalidateTouristSpots();
        CacheInvalidationService::invalidateLeaderboard();

        return response()->json(['success' => true, 'message' => 'Selected spots approved.']);
    }

    /**
     * Forget only the dashboard-specific cache keys.
     * Pass $municipalityId to also clear that municipality's scoped cache.
     * This is far safer than Cache::flush() which destroys analytics/leaderboard/fare caches.
     */
    private function forgetDashboardCaches(?int $municipalityId = null): void
    {
        // Province-wide dashboard caches (LUPTO/PICTO roles)
        foreach (['lupto', 'picto'] as $role) {
            Cache::forget("dashboard:data:{$role}:0");
        }

        // Municipality-scoped dashboard cache
        if ($municipalityId) {
            Cache::forget("dashboard:data:municipal:{$municipalityId}");
            // Flush all known municipal role variants for this municipality
            $municipalRoles = \App\Models\User::$MUNICIPAL_ROLES;
            foreach ($municipalRoles as $role) {
                Cache::forget("dashboard:data:{$role}:{$municipalityId}");
            }
        }

        // Public map cache (approved spots changed)
        Cache::forget('map:public:spots');
    }
}
