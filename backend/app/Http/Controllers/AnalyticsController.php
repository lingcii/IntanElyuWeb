<?php

namespace App\Http\Controllers;

use App\Models\Analytics;
use App\Models\Municipality;
use App\Models\TouristSpot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    private function isMunicipal(): bool
    {
        $role = session('user_role', '');
        return in_array($role, User::$MUNICIPAL_ROLES) && (int) session('user_municipality_id', 0) > 0;
    }

    private function municipalityId(): int
    {
        return (int) session('user_municipality_id', 0);
    }

    private function role(): string
    {
        return session('user_role', 'guest');
    }

    private function scopeKey(string $base): string
    {
        $muniId = $this->isMunicipal() ? $this->municipalityId() : 0;
        return "{$base}:{$this->role()}:{$muniId}";
    }

    private function ensureAnalyticsSynced(): void
    {
        $spotVisitsTotal = (int) DB::table('tourist_spots')->where('status', 'approved')->sum('visits');
        $analyticsTotal  = (int) DB::table('analytics')->sum('visits');

        if ($spotVisitsTotal > 0 && $analyticsTotal === 0) {
            $currentYear  = (int) date('Y');
            $currentMonth = (int) date('n');

            $spots = DB::table('tourist_spots')->where('status', 'approved')->where('visits', '>', 0)->get();
            foreach ($spots as $spot) {
                $v     = (int) $spot->visits;
                $car   = (int) round($v * 0.5);
                $van   = (int) round($v * 0.3);
                $bus   = (int) round($v * 0.1);
                $other = max(0, $v - ($car + $van + $bus));

                DB::table('analytics')->updateOrInsert(
                    [
                        'municipality_id' => $spot->municipality_id,
                        'tourist_spot_id' => $spot->id,
                        'year'            => $currentYear,
                        'month'           => $currentMonth,
                    ],
                    [
                        'visits'          => $v,
                        'transport_car'   => $car,
                        'transport_van'   => $van,
                        'transport_bus'   => $bus,
                        'transport_other' => $other,
                    ]
                );
            }
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $this->ensureAnalyticsSynced();
        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;
        $cacheKey = $this->scopeKey('analytics:summary-v9');
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 3600, function () use ($isMuni, $muniId) {
            $currentYear = now()->year;

            // ── Spots aggregate (single query)
            $spotQuery = DB::table('tourist_spots')->where('status', '!=', 'draft');
            if ($isMuni) $spotQuery->where('municipality_id', $muniId);
            $row = $spotQuery->selectRaw(
                "COUNT(*) as total,
                 SUM(status='approved') as approved,
                 AVG(rating) as avg_rating,
                 SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_spots"
            )->first();

            // Top category (separate because GROUP BY needed)
            $topCatQuery = DB::table('tourist_spots')->selectRaw('category, COUNT(*) as cnt');
            if ($isMuni) $topCatQuery->where('municipality_id', $muniId);
            $topCat = $topCatQuery->groupBy('category')->orderByDesc('cnt')->first();

            // ── Analytics aggregates (single query covering all years needed)
            $muniFilter = $isMuni ? "AND municipality_id = {$muniId}" : '';
            $analyticsRow = DB::selectOne("
                SELECT
                    COALESCE(SUM(visits),0) as total_visits,
                    COALESCE(SUM(CASE WHEN year = {$currentYear} THEN visits ELSE 0 END),0) as this_year,
                    COALESCE(SUM(CASE WHEN year = " . ($currentYear - 1) . " THEN visits ELSE 0 END),0) as prev_year
                FROM analytics
                WHERE 1=1 {$muniFilter}
            ");

            $totalVisits        = (int) ($analyticsRow->total_visits ?? 0);
            $thisYearVisits     = (int) ($analyticsRow->this_year ?? 0);
            $prevYearVisits     = (int) ($analyticsRow->prev_year ?? 0);
            $totalAnalyticsVisits = $totalVisits;

            $visitsYoY = 0.0;
            if ($prevYearVisits > 0) {
                $visitsYoY = round((($thisYearVisits - $prevYearVisits) / $prevYearVisits) * 100, 1);
            }

            // ── MoM trend (2 most recent months)
            $lastMonthsQuery = DB::table('analytics')->where('year', $currentYear);
            if ($isMuni) $lastMonthsQuery->where('municipality_id', $muniId);
            $lastMonths = $lastMonthsQuery->selectRaw('month, SUM(visits) as v')->groupBy('month')->orderByDesc('month')->limit(2)->get();

            $monthPct = 0.0;
            $prevMonthName = '';
            if ($lastMonths->count() >= 2) {
                $curM  = $lastMonths[0];
                $prevM = $lastMonths[1];
                if ($prevM->v > 0) {
                    $monthPct = round((($curM->v - $prevM->v) / $prevM->v) * 100, 1);
                }
                $monthsList    = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                $prevMonthName = $monthsList[($prevM->month - 1)] ?? '';
            }

            // ── Users (single query)
            $usersRow = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_30d
                FROM users WHERE role = 'tourist'
            ");

            // ── Most visited municipality & spot
            $mvMuniQuery = Municipality::leftJoin('tourist_spots as ts', 'ts.municipality_id', '=', 'municipalities.id')
                ->selectRaw('municipalities.name, COALESCE(SUM(ts.visits),0) as v')
                ->groupBy('municipalities.id', 'municipalities.name');
            if ($isMuni) $mvMuniQuery->where('municipalities.id', $muniId);
            $mvMuni = $mvMuniQuery->orderByDesc('v')->first();

            $mvSpotQuery = TouristSpot::query();
            if ($isMuni) $mvSpotQuery->where('municipality_id', $muniId);
            $mvSpot = $mvSpotQuery->orderByDesc('visits')->first(['name', 'visits']);

            $totalMunis = $isMuni ? 1 : Municipality::count();

            return [
                'total_municipalities'   => (int) $totalMunis,
                'total_spots'            => (int) ($row->total ?? 0),
                'approved_spots'         => (int) ($row->approved ?? 0),
                'total_visits'           => $totalVisits,
                'total_analytics_visits' => $totalAnalyticsVisits,
                'total_users'            => (int) ($usersRow->total ?? 0),
                'new_users_30d'          => (int) ($usersRow->new_30d ?? 0),
                'most_visited_muni'      => $mvMuni->name ?? '—',
                'most_visited_muni_v'    => (int) ($mvMuni->v ?? 0),
                'most_visited_spot'      => $mvSpot->name ?? '—',
                'most_visited_spot_v'    => (int) ($mvSpot->visits ?? 0),
                'avg_rating'             => round((float) ($row->avg_rating ?? 0), 2),
                'new_spots_30d'          => (int) ($row->new_spots ?? 0),
                'visits_yoy_pct'         => $visitsYoY,
                'visits_month_pct'       => $monthPct,
                'visits_prev_month'      => $prevMonthName,
                'top_category'           => $topCat->category ?? 'None',
                'top_category_cnt'       => (int) ($topCat->cnt ?? 0),
            ];
        });

        return $this->etagResponse($request, ['success' => true, 'summary' => $data]);
    }

    /**
     * Consolidated dashboard endpoint — returns summary + chart_data + monthly_trend + top_spots
     * in one single cached HTTP request, replacing 4 separate API calls from the frontend.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->ensureAnalyticsSynced();
        $year   = (int) $request->get('year', now()->year);
        $muniId = (int) $request->get('municipality_id', 0);
        $isMuni = $this->isMunicipal();
        $scopedMuniId = $isMuni ? $this->municipalityId() : 0;
        $effectiveMuniId = $isMuni ? $scopedMuniId : $muniId;

        $cacheKey = $this->scopeKey("analytics:dashboard-v3:{$year}:{$effectiveMuniId}");
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        } else if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (empty($cached['summary']['total_visits']) || empty($cached['trend_current'])) {
                Cache::forget($cacheKey);
            }
        }

        $data = Cache::remember($cacheKey, 1800, function () use ($year, $isMuni, $scopedMuniId, $effectiveMuniId) {
            $currentYear  = now()->year;
            $muniFilter   = $effectiveMuniId ? "AND municipality_id = {$effectiveMuniId}" : '';

            // ── 1. SUMMARY (collapsed into 3 queries instead of ~10)
            $spotQuery = DB::table('tourist_spots')->where('status', '!=', 'draft');
            if ($effectiveMuniId) $spotQuery->where('municipality_id', $effectiveMuniId);
            $spotRow = $spotQuery->selectRaw(
                "COUNT(*) as total,
                 SUM(status='approved') as approved,
                 AVG(rating) as avg_rating,
                 SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_spots"
            )->first();

            $topCatQuery = DB::table('tourist_spots')->selectRaw('category, COUNT(*) as cnt');
            if ($effectiveMuniId) $topCatQuery->where('municipality_id', $effectiveMuniId);
            $topCat = $topCatQuery->groupBy('category')->orderByDesc('cnt')->first();

            $analyticsRow = DB::selectOne("
                SELECT
                    COALESCE(SUM(visits),0) as total_visits,
                    COALESCE(SUM(CASE WHEN year = {$currentYear} THEN visits ELSE 0 END),0) as this_year,
                    COALESCE(SUM(CASE WHEN year = " . ($currentYear - 1) . " THEN visits ELSE 0 END),0) as prev_year
                FROM analytics WHERE 1=1 {$muniFilter}
            ");
            $thisYearVisits = (int) ($analyticsRow->this_year ?? 0);
            $prevYearVisits = (int) ($analyticsRow->prev_year ?? 0);
            $visitsYoY = $prevYearVisits > 0
                ? round((($thisYearVisits - $prevYearVisits) / $prevYearVisits) * 100, 1)
                : 0.0;

            $lastMonthsQuery = DB::table('analytics')->where('year', $currentYear);
            if ($effectiveMuniId) $lastMonthsQuery->where('municipality_id', $effectiveMuniId);
            $lastMonths = $lastMonthsQuery->selectRaw('month, SUM(visits) as v')->groupBy('month')->orderByDesc('month')->limit(2)->get();
            $monthPct = 0.0; $prevMonthName = '';
            if ($lastMonths->count() >= 2) {
                $curM = $lastMonths[0]; $prevM = $lastMonths[1];
                if ($prevM->v > 0) $monthPct = round((($curM->v - $prevM->v) / $prevM->v) * 100, 1);
                $ml = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                $prevMonthName = $ml[($prevM->month - 1)] ?? '';
            }

            $usersRow = DB::selectOne("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_30d
                FROM users WHERE role = 'tourist'
            ");

            $mvMuniQ = Municipality::leftJoin('tourist_spots as ts','ts.municipality_id','=','municipalities.id')
                ->selectRaw('municipalities.name, COALESCE(SUM(ts.visits),0) as v')
                ->groupBy('municipalities.id','municipalities.name');
            if ($effectiveMuniId) $mvMuniQ->where('municipalities.id', $effectiveMuniId);
            $mvMuni = $mvMuniQ->orderByDesc('v')->first();

            $mvSpotQ = TouristSpot::query();
            if ($effectiveMuniId) $mvSpotQ->where('municipality_id', $effectiveMuniId);
            $mvSpot = $mvSpotQ->orderByDesc('visits')->first(['name','visits']);

            $totalMunis = $effectiveMuniId ? 1 : Municipality::count();

            $summary = [
                'total_municipalities'   => (int) $totalMunis,
                'total_spots'            => (int) ($spotRow->total ?? 0),
                'approved_spots'         => (int) ($spotRow->approved ?? 0),
                'total_visits'           => (int) ($analyticsRow->total_visits ?? 0),
                'total_analytics_visits' => (int) ($analyticsRow->total_visits ?? 0),
                'total_users'            => (int) ($usersRow->total ?? 0),
                'new_users_30d'          => (int) ($usersRow->new_30d ?? 0),
                'most_visited_muni'      => $mvMuni->name ?? '—',
                'most_visited_muni_v'    => (int) ($mvMuni->v ?? 0),
                'most_visited_spot'      => $mvSpot->name ?? '—',
                'most_visited_spot_v'    => (int) ($mvSpot->visits ?? 0),
                'avg_rating'             => round((float) ($spotRow->avg_rating ?? 0), 2),
                'new_spots_30d'          => (int) ($spotRow->new_spots ?? 0),
                'visits_yoy_pct'         => $visitsYoY,
                'visits_month_pct'       => $monthPct,
                'visits_prev_month'      => $prevMonthName,
                'top_category'           => $topCat->category ?? 'None',
                'top_category_cnt'       => (int) ($topCat->cnt ?? 0),
            ];

            // ── 2. MONTHLY TREND
            $curQ = Analytics::where('year', $year);
            $preQ = Analytics::where('year', $year - 1);
            if ($effectiveMuniId) { $curQ->where('municipality_id', $effectiveMuniId); $preQ->where('municipality_id', $effectiveMuniId); }
            $trendCurrent  = $curQ->selectRaw('month, SUM(visits) as visits')->groupBy('month')->orderBy('month')->get();
            $trendPrevious = $preQ->selectRaw('month, SUM(visits) as visits')->groupBy('month')->orderBy('month')->get();

            // ── 3. CHART DATA
            $tsVisitsSub = DB::table('tourist_spots')
                ->selectRaw('municipality_id, COALESCE(SUM(visits), 0) as spot_visits')
                ->groupBy('municipality_id');
            $anVisitsSub = DB::table('analytics')
                ->where('year', $year)
                ->selectRaw('municipality_id, COALESCE(SUM(visits), 0) as analytics_visits')
                ->groupBy('municipality_id');
            $muniStatsQuery = Municipality::leftJoinSub($anVisitsSub,'an','an.municipality_id','=','municipalities.id')
                ->leftJoinSub($tsVisitsSub,'ts','ts.municipality_id','=','municipalities.id')
                ->selectRaw('municipalities.id, municipalities.name, GREATEST(COALESCE(an.analytics_visits,0), COALESCE(ts.spot_visits,0)) as total_visits');
            if ($effectiveMuniId) $muniStatsQuery->where('municipalities.id', $effectiveMuniId);
            $muniStats = $muniStatsQuery->groupBy('municipalities.id','municipalities.name','an.analytics_visits','ts.spot_visits')->get();

            $spotCounts = DB::table('tourist_spots')->selectRaw('municipality_id, COUNT(*) as count')->groupBy('municipality_id')->pluck('count','municipality_id');
            $muniStats->map(function($item) use ($spotCounts) {
                $item->spot_count    = (int) ($spotCounts[$item->id] ?? 0);
                $item->total_visits  = (int) $item->total_visits;
                return $item;
            });
            $visitsByMuni = $muniStats->sortByDesc('total_visits')->take(10)->values();
            $spotsByMuni  = $muniStats->sortByDesc('spot_count')->take(10)->values();

            $spotsQ = TouristSpot::query();
            if ($effectiveMuniId) $spotsQ->where('municipality_id', $effectiveMuniId);
            $allSpots = $spotsQ->get(['category','rating','visits','classification_status']);

            $catAgg = [];
            foreach ($allSpots as $spot) {
                $rawCats = array_map('trim', explode(',', $spot->category ?? 'Other'));
                foreach ($rawCats as $cat) {
                    if ($cat === '') $cat = 'Other';
                    if (!isset($catAgg[$cat])) $catAgg[$cat] = ['category'=>$cat,'cnt'=>0,'sum_rating'=>0,'rating_count'=>0,'total_visits'=>0];
                    $catAgg[$cat]['cnt']++;
                    $catAgg[$cat]['total_visits'] += (int)($spot->visits ?? 0);
                    if ($spot->rating !== null) { $catAgg[$cat]['sum_rating'] += (float)$spot->rating; $catAgg[$cat]['rating_count']++; }
                }
            }
            $catDist = collect(array_values($catAgg))->map(function($c){
                $c['avg_rating'] = $c['rating_count'] > 0 ? round($c['sum_rating']/$c['rating_count'],2) : 0;
                unset($c['sum_rating'],$c['rating_count']); return (object)$c;
            })->sortByDesc('cnt')->values();

            $classAgg = [];
            foreach ($allSpots as $spot) {
                $rawStatus = strtoupper(trim($spot->classification_status ?? ''));
                $cls = TouristSpot::$STATUS_MAP[$rawStatus] ?? ($rawStatus ?: 'POTENTIAL');
                if (!isset($classAgg[$cls])) $classAgg[$cls] = ['cls'=>$cls,'cnt'=>0,'sum_rating'=>0,'rating_count'=>0];
                $classAgg[$cls]['cnt']++;
                if ($spot->rating !== null) { $classAgg[$cls]['sum_rating'] += (float)$spot->rating; $classAgg[$cls]['rating_count']++; }
            }
            $classDist = collect(array_values($classAgg))->map(function($c){
                $c['avg_rating'] = $c['rating_count'] > 0 ? round($c['sum_rating']/$c['rating_count'],2) : 0;
                unset($c['sum_rating'],$c['rating_count']); return (object)$c;
            })->sortByDesc('cnt')->values();

            $monthlyQ = Analytics::where('year', $year);
            if ($effectiveMuniId) $monthlyQ->where('municipality_id', $effectiveMuniId);
            $monthly = $monthlyQ->selectRaw('month, SUM(visits) as total_visits, SUM(transport_car) as car, SUM(transport_bus) as bus, SUM(transport_van) as van, SUM(transport_other) as other')
                ->groupBy('month')->orderBy('month')->get();

            $transportQ = Analytics::where('year', $year);
            if ($effectiveMuniId) $transportQ->where('municipality_id', $effectiveMuniId);
            $transport = $transportQ->selectRaw('SUM(transport_car) as car, SUM(transport_bus) as bus, SUM(transport_van) as van, SUM(transport_other) as other, SUM(visits) as total')->first();

            // ── 4. TOP SPOTS
            $topSpotsQ = TouristSpot::select([
                'id','name','municipality_id','barangay','category','classification_status',
                'entrance_fee','visits','rating','photo_url','status','points','created_at'
            ])->with('municipality:id,name');
            if ($effectiveMuniId) $topSpotsQ->where('municipality_id', $effectiveMuniId);
            $topSpots = $topSpotsQ->orderByDesc('visits')->limit(10)->get()->values()->map(function($r,$i){
                $r->rank = $i + 1; return $r;
            });

            // ── 5. FILTER OPTIONS
            $munisQ = Municipality::orderBy('name');
            if ($effectiveMuniId) $munisQ->where('id', $effectiveMuniId);
            $munis = $munisQ->get(['id','name']);

            return [
                'summary'        => $summary,
                'trend_current'  => json_decode(json_encode($trendCurrent), true),
                'trend_previous' => json_decode(json_encode($trendPrevious), true),
                'spots_by_muni'  => json_decode(json_encode($spotsByMuni), true),
                'visits_by_muni' => json_decode(json_encode($visitsByMuni), true),
                'cat_dist'       => json_decode(json_encode($catDist), true),
                'class_dist'     => json_decode(json_encode($classDist), true),
                'monthly_visits' => json_decode(json_encode($monthly), true),
                'transport'      => json_decode(json_encode($transport), true),
                'spots'          => json_decode(json_encode($topSpots), true),
                'municipalities' => json_decode(json_encode($munis), true),
                'year'           => $year,
            ];
        });

        return response()->json(['success' => true] + $data);
    }

    public function topMunicipalities(Request $request): JsonResponse
    {
        $sortBy       = $request->get('sort', 'total_visits');
        $filterCat    = $request->filled('category') ? $request->get('category') : '';
        $filterStatus = $request->filled('spot_status') ? $request->get('spot_status') : '';
        $limit        = min((int) $request->get('limit', 10), 20);
        $isMuni       = $this->isMunicipal();
        $muniId       = $isMuni ? $this->municipalityId() : 0;

        $cacheKey = $this->scopeKey("analytics:top-municipalities-v5:{$sortBy}:{$filterCat}:{$filterStatus}:{$limit}");
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        }

        $rows = Cache::remember($cacheKey, 3600, function () use ($sortBy, $filterCat, $filterStatus, $limit, $isMuni, $muniId) {
            $sortMap = [
                'total_visits'   => 'total_visits',
                'total_spots'    => 'total_spots',
                'approved_spots' => 'approved_spots',
                'avg_rating'     => 'avg_rating',
            ];
            $sortCol = $sortMap[$sortBy] ?? 'total_visits';

            $subQuery = DB::table('tourist_spots')
                ->selectRaw("municipality_id,
                    COUNT(*) as total_spots,
                    SUM(status='approved') as approved_spots,
                    COALESCE(SUM(visits),0) as total_visits,
                    COALESCE(AVG(rating),0) as avg_rating");

            if ($isMuni)            $subQuery->where('municipality_id', $muniId);
            if ($filterCat !== '')    $subQuery->where('category', $filterCat);
            if ($filterStatus !== '') $subQuery->where('classification_status', $filterStatus);

            $subQuery->groupBy('municipality_id');

            $analyticsSub = DB::raw('(SELECT municipality_id, SUM(visits) as analytics_visits FROM analytics GROUP BY municipality_id) an');

            $q = Municipality::leftJoinSub($subQuery, 'ts', 'ts.municipality_id', '=', 'municipalities.id')
                ->leftJoin($analyticsSub, 'an.municipality_id', '=', 'municipalities.id')
                ->selectRaw("municipalities.id, municipalities.name, municipalities.attraction_count,
                    COALESCE(ts.total_spots,0) as total_spots,
                    COALESCE(ts.approved_spots,0) as approved_spots,
                    COALESCE(ts.total_visits,0) as total_visits,
                    COALESCE(ts.avg_rating,0) as avg_rating,
                    COALESCE(an.analytics_visits,0) as analytics_visits");

            if ($isMuni) $q->where('municipalities.id', $muniId);

            return $q->orderByDesc($sortCol)
                ->limit($limit)
                ->get()
                ->values()
                ->map(function ($r, $i) {
                    $r->rank             = $i + 1;
                    $r->total_spots      = (int) $r->total_spots;
                    $r->approved_spots   = (int) $r->approved_spots;
                    $r->total_visits     = (int) $r->total_visits;
                    $r->avg_rating       = round((float) $r->avg_rating, 1);
                    $r->analytics_visits = (int) $r->analytics_visits;
                    return $r;
                });
        });

        return $this->etagResponse($request, ['success' => true, 'municipalities' => $rows]);
    }

    public function topSpots(Request $request): JsonResponse
    {
        $sortBy       = $request->get('sort', 'visits');
        $filterMuni   = (int) $request->get('municipality_id', 0);
        $filterCat    = $request->get('category', '');
        $filterStatus = $request->get('spot_status', '');
        $limit        = min((int) $request->get('limit', 10), 20);
        $isMuni       = $this->isMunicipal();
        $muniId       = $isMuni ? $this->municipalityId() : 0;

        $cacheKey = $this->scopeKey("analytics:top-spots-v5:{$sortBy}:{$filterMuni}:{$filterCat}:{$filterStatus}:{$limit}");
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        }

        $rows = Cache::remember($cacheKey, 60, function () use ($sortBy, $filterMuni, $filterCat, $filterStatus, $limit, $isMuni, $muniId) {
            $sortMap = ['visits' => 'visits', 'rating' => 'rating', 'newest' => 'created_at'];
            $sortCol = $sortMap[$sortBy] ?? 'visits';

            $query = TouristSpot::select([
                'id', 'name', 'municipality_id', 'barangay', 'category', 'classification_status',
                'entrance_fee', 'visits', 'rating', 'photo_url', 'status', 'points', 'created_at'
            ])->with('municipality:id,name');

            if ($isMuni) {
                $query->where('municipality_id', $muniId);
            } elseif ($filterMuni) {
                $query->where('municipality_id', $filterMuni);
            }
            if ($filterCat)   $query->where('category', $filterCat);
            if ($filterStatus) $query->where('classification_status', $filterStatus);

            return $query->orderByDesc($sortCol)->limit($limit)->get()
                ->values()->map(function ($r, $i) {
                    $r->rank = $i + 1;
                    return $r;
                });
        });

        return $this->etagResponse($request, ['success' => true, 'spots' => $rows]);
    }

    public function chartData(Request $request): JsonResponse
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', 0);
        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;

        $cacheKey = $this->scopeKey("analytics:chart-data-v10:{$year}:{$month}");
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 3600, function () use ($year, $month, $isMuni, $muniId) {
            $tsVisitsSub = DB::table('tourist_spots')
                ->selectRaw('municipality_id, COALESCE(SUM(visits), 0) as spot_visits')
                ->groupBy('municipality_id');

            $anVisitsSub = DB::table('analytics')
                ->where('year', $year)
                ->selectRaw('municipality_id, COALESCE(SUM(visits), 0) as analytics_visits')
                ->groupBy('municipality_id');

            $muniStatsQuery = Municipality::leftJoinSub($anVisitsSub, 'an', 'an.municipality_id', '=', 'municipalities.id')
                ->leftJoinSub($tsVisitsSub, 'ts', 'ts.municipality_id', '=', 'municipalities.id')
                ->selectRaw('municipalities.id, municipalities.name, GREATEST(COALESCE(an.analytics_visits, 0), COALESCE(ts.spot_visits, 0)) as total_visits');
            if ($isMuni) $muniStatsQuery->where('municipalities.id', $muniId);
            $muniStats = $muniStatsQuery->groupBy('municipalities.id', 'municipalities.name', 'an.analytics_visits', 'ts.spot_visits')->get();

            $spotCounts = DB::table('tourist_spots')
                ->selectRaw('municipality_id, COUNT(*) as count')
                ->groupBy('municipality_id')
                ->pluck('count', 'municipality_id');

            $muniStats->map(function($item) use ($spotCounts) {
                $item->spot_count = (int) ($spotCounts[$item->id] ?? 0);
                $item->total_visits = (int) $item->total_visits;
                return $item;
            });

            $spotsByMuni  = $muniStats->sortByDesc('spot_count')->take(10)->values();
            $visitsByMuni = $muniStats->sortByDesc('total_visits')->take(10)->values();

            // Split comma-delimited categories into individual category rows
            $spotsQuery = TouristSpot::query();
            if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
            $allSpots = $spotsQuery->get(['category', 'rating', 'visits', 'classification_status']);

            $catAgg = [];
            foreach ($allSpots as $spot) {
                $rawCats = array_map('trim', explode(',', $spot->category ?? 'Other'));
                foreach ($rawCats as $cat) {
                    if ($cat === '') $cat = 'Other';
                    if (!isset($catAgg[$cat])) {
                        $catAgg[$cat] = ['category' => $cat, 'cnt' => 0, 'sum_rating' => 0, 'rating_count' => 0, 'total_visits' => 0];
                    }
                    $catAgg[$cat]['cnt']++;
                    $catAgg[$cat]['total_visits'] += (int) ($spot->visits ?? 0);
                    if ($spot->rating !== null) {
                        $catAgg[$cat]['sum_rating'] += (float) $spot->rating;
                        $catAgg[$cat]['rating_count']++;
                    }
                }
            }
            $catDist = collect(array_values($catAgg))->map(function ($c) {
                $c['avg_rating'] = $c['rating_count'] > 0 ? round($c['sum_rating'] / $c['rating_count'], 2) : 0;
                unset($c['sum_rating'], $c['rating_count']);
                return (object) $c;
            })->sortByDesc('cnt')->values();

            // Classification: include ALL spots, mapping to standard status keys
            $classAgg = [];
            foreach ($allSpots as $spot) {
                $rawStatus = strtoupper(trim($spot->classification_status ?? ''));
                $cls = TouristSpot::$STATUS_MAP[$rawStatus] ?? ($rawStatus ?: 'POTENTIAL');
                if (!isset($classAgg[$cls])) {
                    $classAgg[$cls] = ['cls' => $cls, 'cnt' => 0, 'sum_rating' => 0, 'rating_count' => 0];
                }
                $classAgg[$cls]['cnt']++;
                if ($spot->rating !== null) {
                    $classAgg[$cls]['sum_rating'] += (float) $spot->rating;
                    $classAgg[$cls]['rating_count']++;
                }
            }
            $classDist = collect(array_values($classAgg))->map(function ($c) {
                $c['avg_rating'] = $c['rating_count'] > 0 ? round($c['sum_rating'] / $c['rating_count'], 2) : 0;
                unset($c['sum_rating'], $c['rating_count']);
                return (object) $c;
            })->sortByDesc('cnt')->values();

            $monthlyQuery = Analytics::where('year', $year);
            if ($isMuni) $monthlyQuery->where('municipality_id', $muniId);
            if ($month) $monthlyQuery->where('month', $month);
            $monthly = $monthlyQuery->selectRaw('month, SUM(visits) as total_visits, SUM(transport_car) as car, SUM(transport_bus) as bus, SUM(transport_van) as van, SUM(transport_other) as other')
                ->groupBy('month')->orderBy('month')->get();

            $transportQuery = Analytics::where('year', $year);
            if ($isMuni) $transportQuery->where('municipality_id', $muniId);
            $transport = $transportQuery->selectRaw('SUM(transport_car) as car, SUM(transport_bus) as bus, SUM(transport_van) as van, SUM(transport_other) as other, SUM(visits) as total')
                ->first();

            return [
                'spots_by_muni'  => $spotsByMuni,
                'visits_by_muni' => $visitsByMuni,
                'cat_dist'       => $catDist,
                'monthly_visits' => $monthly,
                'class_dist'     => $classDist,
                'transport'      => $transport,
            ];
        });

        return $this->etagResponse($request, [
            'success'        => true,
            'spots_by_muni'  => $data['spots_by_muni'],
            'visits_by_muni' => $data['visits_by_muni'],
            'cat_dist'       => $data['cat_dist'],
            'monthly_visits' => $data['monthly_visits'],
            'class_dist'     => $data['class_dist'],
            'transport'      => $data['transport'],
        ]);
 }

    public function monthlyTrend(Request $request): JsonResponse
    {
        $year   = (int) $request->get('year', now()->year);
        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;

        $cacheKey = $this->scopeKey("analytics:monthly-trend-v5:{$year}");
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 3600, function () use ($year, $isMuni, $muniId) {
            $currentQuery = Analytics::where('year', $year);
            $prevQuery    = Analytics::where('year', $year - 1);
            if ($isMuni) { $currentQuery->where('municipality_id', $muniId); $prevQuery->where('municipality_id', $muniId); }

            $current  = $currentQuery->selectRaw('month, SUM(visits) as visits')->groupBy('month')->orderBy('month')->get();
            $previous = $prevQuery->selectRaw('month, SUM(visits) as visits')->groupBy('month')->orderBy('month')->get();
            return ['current' => $current, 'previous' => $previous];
        });

        return $this->etagResponse($request, [
            'success'  => true,
            'current'  => $data['current'],
            'previous' => $data['previous'],
            'year'     => $year,
        ]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;
        $cacheKey = $this->scopeKey('analytics:filter-options-v5');
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
        }

        $data = Cache::remember($cacheKey, 3600, function () use ($isMuni, $muniId) {
            $munisQuery = Municipality::orderBy('name');
            if ($isMuni) $munisQuery->where('id', $muniId);
            $munis = $munisQuery->get(['id', 'name']);

            $yearsQuery = Analytics::query();
            if ($isMuni) $yearsQuery->where('municipality_id', $muniId);
            $years = $yearsQuery->distinct()->orderByDesc('year')->pluck('year');

            $catQuery = TouristSpot::select('category')->distinct();
            if ($isMuni) $catQuery->where('municipality_id', $muniId);
            $categories = $catQuery->whereNotNull('category')->orderBy('category')->pluck('category')->toArray();

            if (empty($categories)) {
                $categories = ['Beach', 'Mountain', 'Historical', 'Waterfalls', 'Adventure', 'Farm', 'Religious', 'Other'];
            }

            return ['munis' => $munis, 'years' => $years, 'categories' => $categories];
        });

        return $this->etagResponse($request, [
            'success'       => true,
            'municipalities' => $data['munis'],
            'categories'    => $data['categories'],
            'years'         => $data['years'],
        ]);
    }

    public function full(Request $request): JsonResponse
    {
        $year   = (int) $request->get('year', now()->year);
        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;

        $cacheKey = $this->scopeKey("analytics:full-v3:{$year}");

        $data = Cache::remember($cacheKey, 300, function () use ($year, $isMuni, $muniId) {
            $trendsQuery = Analytics::selectRaw('year, month, SUM(visits) as total_visits');
            if ($isMuni) $trendsQuery->where('municipality_id', $muniId);
            $monthlyTrends = $trendsQuery->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();

            $spotsQuery = TouristSpot::where('status', 'approved')->with('municipality:id,name');
            if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
            $topSpots = $spotsQuery->orderByDesc('visits')->limit(5)->get();

            $transportQuery = Analytics::where('year', $year);
            if ($isMuni) $transportQuery->where('municipality_id', $muniId);
            $transportData = $transportQuery->selectRaw('SUM(transport_car) as car, SUM(transport_bus) as bus, SUM(transport_van) as van, SUM(transport_other) as other')
                ->first();

            $rankingsQuery = Municipality::join('analytics as a', 'a.municipality_id', '=', 'municipalities.id')
                ->where('a.year', $year);
            if ($isMuni) $rankingsQuery->where('municipalities.id', $muniId);
            $rankings = $rankingsQuery->selectRaw('municipalities.name, SUM(a.visits) as total_visits')
                ->groupBy('municipalities.id', 'municipalities.name')
                ->orderByDesc('total_visits')->get();

            $costQuery = Municipality::join('analytics as a', 'a.municipality_id', '=', 'municipalities.id')
                ->where('a.year', $year);
            if ($isMuni) $costQuery->where('municipalities.id', $muniId);
            $costBreakdown = $costQuery->selectRaw('municipalities.name, SUM(a.visits) as total_visits')
                ->groupBy('municipalities.id', 'municipalities.name')
                ->orderByDesc('total_visits')->limit(10)->get();

            $muniVisitsQuery = Municipality::join('analytics as a', 'a.municipality_id', '=', 'municipalities.id')
                ->where('a.year', $year)->where('a.month', now()->month);
            if ($isMuni) $muniVisitsQuery->where('municipalities.id', $muniId);
            $municipalityVisits = $muniVisitsQuery->selectRaw('municipalities.name, SUM(a.visits) as total_visits')
                ->groupBy('municipalities.id', 'municipalities.name')
                ->orderByDesc('total_visits')->get();

            return [
                'monthlyTrends'      => $monthlyTrends,
                'topSpots'           => $topSpots,
                'transportData'      => $transportData,
                'rankings'           => $rankings,
                'costBreakdown'      => $costBreakdown,
                'municipalityVisits' => $municipalityVisits,
            ];
        });

        return $this->etagResponse($request, $data);
    }

    public function export(Request $request): mixed
    {
        $format    = strtolower($request->get('format', 'csv'));
        $type      = $request->get('report_type', $request->get('type', 'all_summary'));
        $year      = (int) $request->get('year', now()->year);
        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date');

        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;

        $selectedMuni = $request->get('municipality', $request->get('municipality_id', 'all'));
        if (!$isMuni && $selectedMuni !== 'all' && !empty($selectedMuni)) {
            if (is_numeric($selectedMuni)) {
                $muniId = (int) $selectedMuni;
                $isMuni = true;
            } else {
                $m = Municipality::where('name', 'like', "%{$selectedMuni}%")->first();
                if ($m) {
                    $muniId = $m->id;
                    $isMuni = true;
                }
            }
        }

        if ($format === 'pdf') {
            return $this->exportPdf($type, $year, $isMuni, $muniId, $startDate, $endDate);
        }

        return $this->exportCsv($type, $year, $isMuni, $muniId, $format, $startDate, $endDate);
    }

    private function exportCsv(string $type, int $year, bool $isMuni, int $muniId, string $format = 'csv', ?string $startDate = null, ?string $endDate = null): StreamedResponse
    {
        $ext = ($format === 'excel' || $format === 'xlsx') ? 'xlsx' : 'csv';
        $filename = "analytics_report_{$type}_{$year}_" . date('Ymd_His') . '.' . $ext;
        $mimeType = ($ext === 'xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=utf-8';

        $headers = [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($type, $year, $isMuni, $muniId, $startDate, $endDate) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            $muniName = $isMuni ? (Municipality::find($muniId)?->name ?? 'Municipal') : 'All Municipalities';

            // Document Header
            fputcsv($output, ['OFFICIAL TOURISM MANAGEMENT & ANALYTICS REPORT']);
            fputcsv($output, ['Province of La Union — Intan-Elyu Tourism System']);
            fputcsv($output, ['Date Generated:', date('F j, Y, g:i A')]);
            fputcsv($output, ['Report Type:', strtoupper(str_replace('_', ' ', $type))]);
            fputcsv($output, ['Municipality Scope:', $muniName]);
            fputcsv($output, ['Year Filter:', $year]);
            if ($startDate) fputcsv($output, ['Start Date:', $startDate]);
            if ($endDate)   fputcsv($output, ['End Date:', $endDate]);
            fputcsv($output, ['']);

            // 1. Executive Summary KPIs
            if (in_array($type, ['all_summary', 'full', 'tourist_spots_summary', 'tourism_statistics'])) {
                $summaryData = (new self)->summary(new Request(['year' => $year]))->getData(true);
                $s = $summaryData['summary'] ?? [];

                fputcsv($output, ['=== EXECUTIVE SUMMARY STATISTICS ===']);
                fputcsv($output, ['Metric', 'Value']);
                fputcsv($output, ['Total Tourist Sites', $s['total_spots'] ?? 0]);
                fputcsv($output, ['Approved Tourist Sites', $s['approved_spots'] ?? 0]);
                fputcsv($output, ['Registered Tourist Users', $s['total_users'] ?? 0]);
                fputcsv($output, ['Total Annual Visitor Arrivals', $s['total_visits'] ?? 0]);
                fputcsv($output, ['Top Performing Category', $s['top_category'] ?? '—']);
                fputcsv($output, ['Top Category Spot Count', $s['top_category_cnt'] ?? 0]);
                fputcsv($output, ['Most Visited Municipality', $s['most_visited_muni'] ?? '—']);
                fputcsv($output, ['Most Visited Spot', $s['most_visited_spot'] ?? '—']);
                fputcsv($output, ['Average Overall Rating', $s['avg_rating'] ?? '0.0']);
                fputcsv($output, ['']);
            }

            // 2. Top Tourist Spots
            if (in_array($type, ['all_summary', 'full', 'tourist_spots_summary', 'tourist_spot_ratings'])) {
                fputcsv($output, ['=== TOURIST SPOTS / DESTINATIONS ===']);
                fputcsv($output, ['Rank', 'Destination Name', 'Barangay', 'Municipality', 'Category', 'Visits', 'Rating']);
                $spotsQuery = TouristSpot::where('status', '!=', 'draft')->with('municipality:id,name');
                if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
                if ($startDate) $spotsQuery->whereDate('created_at', '>=', $startDate);
                if ($endDate)   $spotsQuery->whereDate('created_at', '<=', $endDate);
                $spots = $spotsQuery->orderByDesc('visits')->get();
                $rank = 0;
                foreach ($spots as $sp) {
                    $rank++;
                    fputcsv($output, [
                        $rank,
                        $sp->name,
                        $sp->barangay ?? '—',
                        $sp->municipality->name ?? '—',
                        $sp->category,
                        $sp->visits ?? 0,
                        $sp->rating ?? '0.0'
                    ]);
                }
                fputcsv($output, ['']);
            }

            // 3. Municipalities Breakdown
            if (in_array($type, ['all_summary', 'full', 'tourist_spots_by_municipality'])) {
                fputcsv($output, ['=== VISITORS BY MUNICIPALITY ===']);
                fputcsv($output, ['Rank', 'Municipality', 'Total Tourist Sites', 'Total Visits', 'Average Rating']);
                $munis = Municipality::leftJoin('tourist_spots as ts', 'ts.municipality_id', '=', 'municipalities.id')
                    ->selectRaw("municipalities.name, COUNT(ts.id) as total_spots, COALESCE(SUM(ts.visits),0) as total_visits, COALESCE(AVG(ts.rating),0) as avg_rating")
                    ->when($isMuni, fn($q) => $q->where('municipalities.id', $muniId))
                    ->groupBy('municipalities.id', 'municipalities.name')
                    ->orderByDesc('total_visits')->get();
                $rank = 0;
                foreach ($munis as $m) {
                    $rank++;
                    fputcsv($output, [$rank, $m->name, $m->total_spots, $m->total_visits, round($m->avg_rating, 1)]);
                }
                fputcsv($output, ['']);
            }

            // 4. Monthly Visitor Trends
            if (in_array($type, ['all_summary', 'full', 'tourism_statistics'])) {
                fputcsv($output, ['=== MONTHLY VISITOR TRENDS (' . $year . ') ===']);
                fputcsv($output, ['Year', 'Month', 'Total Visitors']);
                $trendsQuery = Analytics::where('year', $year)->selectRaw('year, month, SUM(visits) as visits');
                if ($isMuni) $trendsQuery->where('municipality_id', $muniId);
                $trends = $trendsQuery->groupBy('year', 'month')->orderBy('month')->get();
                foreach ($trends as $t) {
                    $monthName = date('F', mktime(0, 0, 0, $t->month, 1));
                    fputcsv($output, [$t->year, $monthName, $t->visits]);
                }
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPdf(string $type, int $year, bool $isMuni, int $muniId, ?string $startDate = null, ?string $endDate = null): mixed
    {
        $role     = $this->role();
        $muniName = $isMuni ? (Municipality::find($muniId)?->name ?? 'Municipal') : 'Province-Wide (All Municipalities)';
        $title    = "Official Tourism Analytics & Reports";
        $dateStr  = date('F j, Y, g:i A');

        // Summary metrics
        $summaryData = (new self)->summary(new Request(['year' => $year]))->getData(true);
        $s = $summaryData['summary'] ?? [];

        // Spots
        $spotsQuery = TouristSpot::where('status', '!=', 'draft')->with('municipality:id,name');
        if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
        if ($startDate) $spotsQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate)   $spotsQuery->whereDate('created_at', '<=', $endDate);
        $spots = $spotsQuery->orderByDesc('visits')->get();

        // Municipalities
        $munis = Municipality::leftJoin('tourist_spots as ts', 'ts.municipality_id', '=', 'municipalities.id')
            ->selectRaw("municipalities.name, COUNT(ts.id) as total_spots, COALESCE(SUM(ts.visits),0) as total_visits, COALESCE(AVG(ts.rating),0) as avg_rating")
            ->when($isMuni, fn($q) => $q->where('municipalities.id', $muniId))
            ->groupBy('municipalities.id', 'municipalities.name')
            ->orderByDesc('total_visits')->get();

        // Monthly trends
        $trendsQuery = Analytics::where('year', $year)->selectRaw('year, month, SUM(visits) as visits');
        if ($isMuni) $trendsQuery->where('municipality_id', $muniId);
        $trends = $trendsQuery->groupBy('year', 'month')->orderBy('month')->get();

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>
            @page { size: A4; margin: 15mm; }
            body { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 24px; color: #1e293b; background: #ffffff; font-size: 13px; line-height: 1.5; }
            .header-banner { border-bottom: 3px double #0F2C59; padding-bottom: 14px; margin-bottom: 20px; }
            .gov-sub { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 1px; margin: 0; }
            .doc-title { font-size: 22px; font-weight: 800; color: #0F2C59; margin: 4px 0 0; text-transform: uppercase; letter-spacing: 0.5px; }
            .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 18px; margin-bottom: 24px; display: table; width: 100%; box-sizing: border-box; }
            .meta-row { display: table-row; }
            .meta-cell { display: table-cell; padding: 4px 12px; }
            .meta-label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.5px; }
            .meta-val { font-size: 13px; font-weight: 700; color: #0F2C59; }

            .section-title { font-size: 13px; font-weight: 700; color: #0F2C59; text-transform: uppercase; border-bottom: 2px solid #0F2C59; padding-bottom: 4px; margin: 24px 0 12px; letter-spacing: 0.5px; }
            .kpi-table { width: 100%; border-collapse: separate; border-spacing: 10px; margin-bottom: 14px; }
            .kpi-td { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; text-align: center; vertical-align: top; }
            .kpi-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
            .kpi-val { font-size: 22px; font-weight: 800; color: #0F2C59; margin-top: 4px; }
            table.report-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
            table.report-table th { background: #0F2C59; color: #ffffff; text-align: left; padding: 9px 12px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
            table.report-table td { padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; }
            table.report-table tr:nth-child(even) td { background: #f8fafc; }
            .rank-num { font-weight: 700; color: #64748b; }
            .footer-bar { margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 12px; text-align: center; font-size: 11px; color: #94a3b8; }
        </style></head><body>';

        // Official Header
        $html .= '<div class="header-banner">';
        $html .= '<p class="gov-sub">Republic of the Philippines &bull; Province of La Union</p>';
        $html .= '<h1 class="doc-title">Official Tourism Analytics &amp; Reports</h1>';
        $html .= '</div>';

        // Metadata Box
        $html .= '<div class="meta-box"><div class="meta-row">';
        $html .= '<div class="meta-cell"><div class="meta-label">Coverage Scope</div><div class="meta-val">' . htmlspecialchars($muniName) . '</div></div>';
        $html .= '<div class="meta-cell"><div class="meta-label">Selected Year</div><div class="meta-val">' . $year . '</div></div>';
        $html .= '<div class="meta-cell"><div class="meta-label">Date Generated</div><div class="meta-val">' . $dateStr . '</div></div>';
        $html .= '<div class="meta-cell"><div class="meta-label">Authorized Role</div><div class="meta-val">' . strtoupper($role) . '</div></div>';
        $html .= '</div></div>';

        // 1. Executive Summary KPIs Table Grid
        $html .= '<div class="section-title"><i class="fas fa-chart-pie"></i> Executive Summary Statistics</div>';
        $html .= '<table class="kpi-table"><tr>';
        $html .= '<td class="kpi-td"><div class="kpi-label">Total Tourist Sites</div><div class="kpi-val">' . number_format($s['total_spots'] ?? 0) . '</div></td>';
        $html .= '<td class="kpi-td"><div class="kpi-label">Approved Sites</div><div class="kpi-val">' . number_format($s['approved_spots'] ?? 0) . '</div></td>';
        $html .= '<td class="kpi-td"><div class="kpi-label">Registered Tourist Users</div><div class="kpi-val">' . number_format($s['total_users'] ?? 0) . '</div></td>';
        $html .= '<td class="kpi-td"><div class="kpi-label">Annual Visitor Arrivals</div><div class="kpi-val">' . number_format($s['total_visits'] ?? 0) . '</div></td>';
        $html .= '</tr></table>';


        // 2. Top Tourist Spots Table
        $html .= '<div class="section-title">Top Tourist Sites / Destinations</div>';
        $html .= '<table class="report-table"><thead><tr><th>#</th><th>Destination Name</th><th>Barangay</th><th>Municipality</th><th>Category</th><th>Visits</th><th>Rating</th></tr></thead><tbody>';
        $rank = 0;
        foreach ($spots as $sp) {
            $rank++;
            $html .= '<tr>';
            $html .= '<td class="rank-num">' . sprintf('%02d', $rank) . '</td>';
            $html .= '<td><strong>' . htmlspecialchars($sp->name) . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($sp->barangay ?? '—') . '</td>';
            $html .= '<td>' . htmlspecialchars($sp->municipality->name ?? '—') . '</td>';
            $html .= '<td><span class="badge-cat">' . htmlspecialchars($sp->category) . '</span></td>';
            $html .= '<td><strong>' . number_format($sp->visits ?? 0) . '</strong></td>';
            $html .= '<td>★ ' . number_format((float)($sp->rating ?? 0), 1) . '</td>';
            $html .= '</tr>';
        }
        if (count($spots) === 0) {
            $html .= '<tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:16px;">No tourist sites recorded for this scope.</td></tr>';
        }
        $html .= '</tbody></table>';

        // 3. Municipalities Comparison
        $html .= '<div class="section-title">Visitors by Municipality</div>';
        $html .= '<table class="report-table"><thead><tr><th>#</th><th>Municipality Name</th><th>Total Tourist Sites</th><th>Total Visitor Arrivals</th><th>Average Rating</th></tr></thead><tbody>';
        $rank = 0;
        foreach ($munis as $m) {
            $rank++;
            $html .= '<tr>';
            $html .= '<td class="rank-num">' . sprintf('%02d', $rank) . '</td>';
            $html .= '<td><strong>' . htmlspecialchars($m->name) . '</strong></td>';
            $html .= '<td>' . number_format($m->total_spots) . '</td>';
            $html .= '<td><strong>' . number_format($m->total_visits) . '</strong></td>';
            $html .= '<td>★ ' . number_format((float)$m->avg_rating, 1) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // 4. Monthly Trend Breakdown
        $html .= '<div class="section-title">Monthly Visitor Trends (' . $year . ')</div>';
        $html .= '<table class="report-table"><thead><tr><th>Month</th><th>Year</th><th>Visitor Arrivals</th></tr></thead><tbody>';
        foreach ($trends as $t) {
            $monthName = date('F', mktime(0, 0, 0, $t->month, 1));
            $html .= '<tr><td>' . $monthName . '</td><td>' . $t->year . '</td><td><strong>' . number_format($t->visits) . '</strong></td></tr>';
        }
        if (count($trends) === 0) {
            $html .= '<tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:16px;">No monthly visitor trend data recorded for ' . $year . '.</td></tr>';
        }
        $html .= '</tbody></table>';

        // Footer
        $html .= '<div class="footer-bar">Intan-Elyu Tourism Management &amp; Information System &bull; Province of La Union &bull; Generated ' . $dateStr . '</div>';
        $html .= '</body></html>';

        $pdfClass = 'Barryvdh\DomPDF\Facade\Pdf';
        if (class_exists($pdfClass)) {
            $pdf = $pdfClass::loadHTML($html);
            return response()->streamDownload(function () use ($pdf) { echo $pdf->output(); }, "analytics_report_{$year}.pdf", ['Content-Type' => 'application/pdf']);
        }

        // Printable HTML view fallback with auto-print
        $printableHtml = str_replace('</body>', '<script>window.onload=function(){setTimeout(function(){window.print();},500);};</script></body>', $html);
        return response($printableHtml, 200, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }
}
