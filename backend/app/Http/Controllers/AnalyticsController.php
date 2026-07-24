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

    public function summary(Request $request): JsonResponse
    {
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
                 SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_spots,
                 category as top_cat_raw"
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
        $year   = (int) $request->get('year', now()->year);
        $muniId = (int) $request->get('municipality_id', 0);
        $isMuni = $this->isMunicipal();
        $scopedMuniId = $isMuni ? $this->municipalityId() : 0;
        $effectiveMuniId = $isMuni ? $scopedMuniId : $muniId;

        $cacheKey = $this->scopeKey("analytics:dashboard-v3:{$year}:{$effectiveMuniId}");
        if ($request->has('refresh') || $request->has('nocache')) {
            Cache::forget($cacheKey);
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

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->get('format', 'csv');
        $type   = $request->get('type', 'summary');
        $year   = (int) $request->get('year', now()->year);
        $isMuni = $this->isMunicipal();
        $muniId = $isMuni ? $this->municipalityId() : 0;

        if ($format === 'pdf') {
            return $this->exportPdf($type, $year, $isMuni, $muniId);
        }

        return $this->exportCsv($type, $year, $isMuni, $muniId);
    }

    private function exportCsv(string $type, int $year, bool $isMuni, int $muniId): StreamedResponse
    {
        $filename = "analytics_{$type}_{$year}_" . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($type, $year, $isMuni, $muniId) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            switch ($type) {
                case 'municipalities':
                    fputcsv($output, ['Rank', 'Municipality', 'Total Spots', 'Approved Spots', 'Total Visits', 'Avg Rating']);
                    $munis = Municipality::leftJoin('tourist_spots as ts', 'ts.municipality_id', '=', 'municipalities.id')
                        ->selectRaw("municipalities.name, COUNT(ts.id) as total_spots, COALESCE(SUM(ts.status='approved'),0) as approved_spots, COALESCE(SUM(ts.visits),0) as total_visits, COALESCE(AVG(ts.rating),0) as avg_rating");
                    if ($isMuni) $munis->where('municipalities.id', $muniId);
                    $munis = $munis->groupBy('municipalities.id', 'municipalities.name')
                        ->orderByDesc('total_visits')->get();
                    $rank = 0;
                    foreach ($munis as $m) {
                        $rank++;
                        fputcsv($output, [$rank, $m->name, $m->total_spots, $m->approved_spots, $m->total_visits, round($m->avg_rating, 1)]);
                    }
                    break;

                case 'spots':
                    fputcsv($output, ['Rank', 'Tourist Spot', 'Municipality', 'Category', 'Status', 'Visits', 'Rating']);
                    $spotsQuery = TouristSpot::with('municipality:id,name');
                    if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
                    $spots = $spotsQuery->orderByDesc('visits')->get();
                    $rank = 0;
                    foreach ($spots as $s) {
                        $rank++;
                        fputcsv($output, [$rank, $s->name, $s->municipality->name ?? '', $s->category, $s->classification_status ?? $s->status, $s->visits, $s->rating]);
                    }
                    break;

                case 'trends':
                    fputcsv($output, ['Year', 'Month', 'Visits']);
                    $trendsQuery = Analytics::selectRaw('year, month, SUM(visits) as visits');
                    if ($isMuni) $trendsQuery->where('municipality_id', $muniId);
                    $trends = $trendsQuery->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();
                    foreach ($trends as $t) {
                        fputcsv($output, [$t->year, date('F', mktime(0, 0, 0, $t->month, 1)), $t->visits]);
                    }
                    break;

                case 'full':
                    fputcsv($output, ['Section: Monthly Trends']);
                    fputcsv($output, ['Year', 'Month', 'Visits']);
                    $trendsQuery = Analytics::selectRaw('year, month, SUM(visits) as visits');
                    if ($isMuni) $trendsQuery->where('municipality_id', $muniId);
                    $trends = $trendsQuery->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();
                    foreach ($trends as $t) {
                        fputcsv($output, [$t->year, date('F', mktime(0, 0, 0, $t->month, 1)), $t->visits]);
                    }
                    fputcsv($output, ['']);
                    fputcsv($output, ['Section: Top Tourist Spots']);
                    fputcsv($output, ['Rank', 'Name', 'Municipality', 'Category', 'Visits', 'Rating']);
                    $spotsQuery = TouristSpot::where('status', 'approved')->with('municipality:id,name');
                    if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
                    $spots = $spotsQuery->orderByDesc('visits')->limit(20)->get();
                    $rank = 0;
                    foreach ($spots as $s) { $rank++; fputcsv($output, [$rank, $s->name, $s->municipality->name ?? '', $s->category, $s->visits, $s->rating]); }
                    break;

                default: // summary
                    $summaryData = (new self)->summary(new Request())->getData(true);
                    $s = $summaryData['summary'] ?? [];
                    fputcsv($output, ['Metric', 'Value']);
                    fputcsv($output, ['Total Municipalities', $s['total_municipalities'] ?? 0]);
                    fputcsv($output, ['Total Tourist Spots', $s['total_spots'] ?? 0]);
                    fputcsv($output, ['Approved Spots', $s['approved_spots'] ?? 0]);
                    fputcsv($output, ['Total Visits', $s['total_visits'] ?? 0]);
                    fputcsv($output, ['Analytics Visits', $s['total_analytics_visits'] ?? 0]);
                    fputcsv($output, ['Active Users', $s['total_users'] ?? 0]);
                    fputcsv($output, ['Most Visited Municipality', $s['most_visited_muni'] ?? '—']);
                    fputcsv($output, ['Most Visited Spot', $s['most_visited_spot'] ?? '—']);
                    break;
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPdf(string $type, int $year, bool $isMuni, int $muniId): JsonResponse|StreamedResponse
    {
        $data = [];

        switch ($type) {
            case 'trends':
                $trendsQuery = Analytics::selectRaw('year, month, SUM(visits) as visits');
                if ($isMuni) $trendsQuery->where('municipality_id', $muniId);
                $data['trends'] = $trendsQuery->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();
                break;
            case 'spots':
                $spotsQuery = TouristSpot::with('municipality:id,name');
                if ($isMuni) $spotsQuery->where('municipality_id', $muniId);
                $data['spots'] = $spotsQuery->orderByDesc('visits')->get();
                break;
            case 'municipalities':
                $data['munis'] = Municipality::leftJoin('tourist_spots as ts', 'ts.municipality_id', '=', 'municipalities.id')
                    ->selectRaw("municipalities.name, COUNT(ts.id) as total_spots, COALESCE(SUM(ts.visits),0) as total_visits, COALESCE(AVG(ts.rating),0) as avg_rating")
                    ->when($isMuni, fn($q) => $q->where('municipalities.id', $muniId))
                    ->groupBy('municipalities.id', 'municipalities.name')
                    ->orderByDesc('total_visits')->get();
                break;
            default:
                $summaryData = (new self)->summary(new Request())->getData(true);
                $data['summary'] = $summaryData['summary'] ?? [];
                break;
        }

        $role    = $this->role();
        $muniName = $isMuni ? Municipality::find($muniId)?->name : 'Province-Wide';
        $title   = "Analytics Report — {$muniName} — {$year}";

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>
            body { font-family: "Segoe UI", Arial, sans-serif; padding: 40px; color: #1e293b; }
            h1 { color: #185FA5; border-bottom: 3px solid #185FA5; padding-bottom: 10px; margin-bottom: 24px; }
            h2 { color: #334155; margin-top: 28px; }
            table { width: 100%; border-collapse: collapse; margin: 12px 0 24px; }
            th { background: #185FA5; color: #fff; padding: 10px 12px; text-align: left; font-size: 13px; text-transform: uppercase; }
            td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
            tr:nth-child(even) td { background: #f8fafc; }
            .kpi-grid { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
            .kpi-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; flex: 1; min-width: 180px; }
            .kpi-card h4 { margin: 0 0 6px; font-size: 11px; color: #64748b; text-transform: uppercase; }
            .kpi-card .val { font-size: 24px; font-weight: 800; color: #185FA5; }
            .footer { margin-top: 40px; color: #94a3b8; font-size: 11px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        </style></head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<p>Generated: ' . date('F j, Y, g:i A') . ' | Role: ' . strtoupper($role) . ' | Scope: ' . htmlspecialchars($muniName) . '</p>';

        if (isset($data['summary'])) {
            $s = $data['summary'];
            $html .= '<h2>Summary</h2><div class="kpi-grid">';
            $kpiItems = [
                'Municipalities' => $s['total_municipalities'] ?? 0,
                'Tourist Spots' => $s['total_spots'] ?? 0,
                'Approved Spots' => $s['approved_spots'] ?? 0,
                'Total Visits' => $s['total_visits'] ?? 0,
                'Analytics Visits' => $s['total_analytics_visits'] ?? 0,
                'Active Users' => $s['total_users'] ?? 0,
            ];
            foreach ($kpiItems as $label => $value) {
                $html .= '<div class="kpi-card"><h4>' . $label . '</h4><div class="val">' . number_format((int)$value) . '</div></div>';
            }
            $html .= '</div>';
            $html .= '<p><strong>Most Visited Municipality:</strong> ' . htmlspecialchars($s['most_visited_muni'] ?? '—') . '</p>';
            $html .= '<p><strong>Most Visited Spot:</strong> ' . htmlspecialchars($s['most_visited_spot'] ?? '—') . '</p>';
        }

        if (isset($data['trends'])) {
            $html .= '<h2>Monthly Trends</h2><table><tr><th>Year</th><th>Month</th><th>Visits</th></tr>';
            foreach ($data['trends'] as $t) {
                $monthName = date('F', mktime(0, 0, 0, $t->month, 1));
                $html .= '<tr><td>' . $t->year . '</td><td>' . $monthName . '</td><td>' . number_format($t->visits) . '</td></tr>';
            }
            $html .= '</table>';
        }

        if (isset($data['spots'])) {
            $html .= '<h2>Tourist Spots</h2><table><tr><th>#</th><th>Name</th><th>Municipality</th><th>Category</th><th>Visits</th><th>Rating</th></tr>';
            $rank = 0;
            foreach ($data['spots'] as $s) { $rank++;
                $html .= '<tr><td>' . $rank . '</td><td>' . htmlspecialchars($s->name) . '</td><td>' . htmlspecialchars($s->municipality->name ?? '') . '</td><td>' . htmlspecialchars($s->category) . '</td><td>' . number_format($s->visits) . '</td><td>' . $s->rating . '</td></tr>';
            }
            $html .= '</table>';
        }

        if (isset($data['munis'])) {
            $html .= '<h2>Top Municipalities</h2><table><tr><th>#</th><th>Municipality</th><th>Spots</th><th>Visits</th><th>Rating</th></tr>';
            $rank = 0;
            foreach ($data['munis'] as $m) { $rank++;
                $html .= '<tr><td>' . $rank . '</td><td>' . htmlspecialchars($m->name) . '</td><td>' . $m->total_spots . '</td><td>' . number_format($m->total_visits) . '</td><td>' . round($m->avg_rating, 1) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= '<div class="footer">Intan Elyu Tourism Management System — Generated on ' . date('F j, Y') . ' at ' . date('g:i A') . '</div>';
        $html .= '</body></html>';

        $pdfClass = 'Barryvdh\DomPDF\Facade\Pdf';
        if (class_exists($pdfClass)) {
            $pdf = $pdfClass::loadHTML($html);
            return response()->streamDownload(function () use ($pdf) { echo $pdf->output(); }, "analytics_{$type}_{$year}.pdf", ['Content-Type' => 'application/pdf']);
        }

        return response()->json([
            'success' => true,
            'format' => 'pdf',
            'html'   => $html,
            'message' => 'PDF library not installed. Opening printable HTML view.',
        ]);
    }
}
