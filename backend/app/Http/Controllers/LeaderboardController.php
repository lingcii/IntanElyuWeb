<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaderboardController extends Controller
{
    /**
     * Clear all leaderboard cache instances instantly across all pages, sorts, and KPIs.
     */
    public static function clearCache(): void
    {
        Cache::forget('leaderboard:top3');
        Cache::forget('leaderboard:kpis');

        // Increment cache version so all paginated/filtered cache entries are invalidated immediately
        $version = (int) Cache::get('leaderboard:cache_version', 1) + 1;
        Cache::forever('leaderboard:cache_version', $version);
    }

    /**
     * Build the Common Table Expression (CTE) for the Leaderboard.
     * 
     * Guarantees:
     * 1. EXACTLY ONE row per active tourist user (unique by u.id).
     * 2. Points and completed activities from all records are properly aggregated (SUM / COALESCE).
     * 3. Excludes inactive, deactivated, pending, deleted, or archived users.
     * 4. Count of active tourists matches User Management 1:1.
     */
    private function rankedCte(): string
    {
        $hasDeletedAt    = Schema::hasColumn('users', 'deleted_at');
        $deletedAtClause = $hasDeletedAt ? ' AND u.deleted_at IS NULL' : '';

        $hasUserPoints = Schema::hasTable('user_points');

        if ($hasUserPoints) {
            $hasTotalPts = Schema::hasColumn('user_points', 'total_points');
            $hasPts      = Schema::hasColumn('user_points', 'points');
            $ptsCol      = $hasTotalPts ? 'total_points' : ($hasPts ? 'points' : '0');
            $hasCompAct  = Schema::hasColumn('user_points', 'completed_activities');
            $actCol      = $hasCompAct ? 'completed_activities' : '0';
            $sinceCol    = Schema::hasColumn('user_points', 'points_since') ? 'points_since' : 'created_at';

            $ptsJoin = "
                LEFT JOIN (
                    SELECT
                        user_id,
                        SUM(COALESCE({$ptsCol}, 0))                   AS total_points,
                        SUM(COALESCE({$actCol}, 0))                   AS completed_activities,
                        MIN(COALESCE({$sinceCol}, NOW()))             AS points_since
                    FROM user_points
                    GROUP BY user_id
                ) pts ON pts.user_id = u.id
            ";
            $ptsExpr   = "COALESCE(pts.total_points, u.points, 0)";
            $actExpr   = "COALESCE(pts.completed_activities, u.completed_activities, 0)";
            $sinceExpr = "COALESCE(pts.points_since, u.created_at)";
        } else {
            $ptsJoin   = "";
            $ptsExpr   = "COALESCE(u.points, 0)";
            $actExpr   = "COALESCE(u.completed_activities, 0)";
            $sinceExpr = "u.created_at";
        }

        return "
            WITH ranked AS (
                SELECT
                    u.id                                              AS user_id,
                    u.name                                            AS full_name,
                    u.role                                            AS role,
                    u.avatar                                          AS avatar,
                    m.name                                            AS municipality_name,
                    u.last_activity                                   AS last_activity_date,
                    {$ptsExpr}                                        AS total_points,
                    {$actExpr}                                        AS completed_activities,
                    {$sinceExpr}                                      AS points_since,
                    0                                                 AS spots_managed,
                    ROW_NUMBER() OVER (
                        ORDER BY
                            {$ptsExpr}                                DESC,
                            {$actExpr}                                DESC,
                            {$sinceExpr}                              ASC,
                            u.id                                      ASC
                    ) AS user_rank
                FROM users u
                {$ptsJoin}
                LEFT JOIN municipalities m ON m.id = u.municipality_id
                WHERE u.role = 'tourist'
                  AND u.status = 'active'
                  {$deletedAtClause}
            )
        ";
    }

    public function top3(): JsonResponse
    {
        $version = Cache::get('leaderboard:cache_version', 1);
        $rows = Cache::remember("leaderboard:v{$version}:top3", 60, function () {
            $rows = DB::select($this->rankedCte() . 'SELECT * FROM ranked WHERE user_rank <= 3 ORDER BY user_rank ASC');
            return $this->castRows($rows);
        });

        return response()->json(['success' => true, 'top3' => $rows]);
    }

    public function kpis(): JsonResponse
    {
        $version = Cache::get('leaderboard:cache_version', 1);
        $kpis = Cache::remember("leaderboard:v{$version}:kpis", 60, function () {
            $hasDeletedAt    = Schema::hasColumn('users', 'deleted_at');
            $deletedAtClause = $hasDeletedAt ? ' AND u.deleted_at IS NULL' : '';

            $hasUserPoints = Schema::hasTable('user_points');

            if ($hasUserPoints) {
                $hasTotalPts = Schema::hasColumn('user_points', 'total_points');
                $hasPts      = Schema::hasColumn('user_points', 'points');
                $ptsCol      = $hasTotalPts ? 'total_points' : ($hasPts ? 'points' : '0');
                $hasCompAct  = Schema::hasColumn('user_points', 'completed_activities');
                $actCol      = $hasCompAct ? 'completed_activities' : '0';

                $ptsJoin = "
                    LEFT JOIN (
                        SELECT
                            user_id,
                            SUM(COALESCE({$ptsCol}, 0))               AS total_points,
                            SUM(COALESCE({$actCol}, 0))               AS completed_activities
                        FROM user_points
                        GROUP BY user_id
                    ) pts ON pts.user_id = u.id
                ";
                $ptsExpr = "COALESCE(pts.total_points, u.points, 0)";
                $actExpr = "COALESCE(pts.completed_activities, u.completed_activities, 0)";
            } else {
                $ptsJoin = "";
                $ptsExpr = "COALESCE(u.points, 0)";
                $actExpr = "COALESCE(u.completed_activities, 0)";
            }

            $kpi = DB::selectOne("
                SELECT
                    COUNT(u.id)                                                AS total_users,
                    COALESCE(SUM({$ptsExpr}), 0)                               AS grand_points,
                    COALESCE(SUM({$actExpr}), 0)                               AS total_activities,
                    COALESCE(MAX({$ptsExpr}), 0)                               AS highest_points
                FROM users u
                {$ptsJoin}
                WHERE u.role = 'tourist'
                  AND u.status = 'active'
                  {$deletedAtClause}
            ");

            return [
                'total_users'      => (int) ($kpi->total_users ?? 0),
                'grand_points'     => (int) ($kpi->grand_points ?? 0),
                'total_activities' => (int) ($kpi->total_activities ?? 0),
                'highest_points'   => (int) ($kpi->highest_points ?? 0),
            ];
        });

        return response()->json(['success' => true, 'kpis' => $kpis]);
    }

    public function index(Request $request): JsonResponse
    {
        $search  = trim((string) $request->get('search', ''));
        $sortBy  = $request->get('sort', 'points_desc');
        $show    = $request->get('show', '100');

        $orderMap = [
            'points_desc'     => 'total_points DESC, completed_activities DESC, points_since ASC, user_id ASC',
            'points_asc'      => 'total_points ASC, completed_activities ASC, points_since DESC, user_id ASC',
            'activities_desc' => 'completed_activities DESC, total_points DESC, points_since ASC, user_id ASC',
            'name_asc'        => 'full_name ASC, user_id ASC',
        ];
        $orderSql = $orderMap[$sortBy] ?? $orderMap['points_desc'];

        $limit  = null;
        $offset = 0;
        if ($show !== 'all') {
            $limit  = min(max((int) $show, 1), 100);
            $offset = max((int) $request->get('offset', 0), 0);
        }

        $whereClause = '';
        $params      = [];

        if ($search !== '') {
            $castType = DB::getDriverName() === 'pgsql' ? 'VARCHAR' : 'CHAR';
            $whereClause = "WHERE full_name LIKE ? OR CAST(user_id AS {$castType}) LIKE ?";
            $params      = ["%{$search}%", "%{$search}%"];
        }

        $version = Cache::get('leaderboard:cache_version', 1);
        $cacheKey = "leaderboard:v{$version}:index:{$search}:{$sortBy}:{$show}:{$offset}";

        $cachedData = Cache::remember($cacheKey, 60, function () use ($whereClause, $params, $orderSql, $limit, $offset, $show) {
            $total = DB::selectOne($this->rankedCte() . "SELECT COUNT(*) as cnt FROM ranked {$whereClause}", $params)->cnt;

            $sql = $this->rankedCte() . "SELECT * FROM ranked {$whereClause} ORDER BY {$orderSql}";

            if ($show === 'all') {
                $sql .= " LIMIT " . max((int) $total, 1);
            } else {
                $sql .= " LIMIT {$limit} OFFSET {$offset}";
            }

            $rows = DB::select($sql, $params);

            return [
                'total' => (int) $total,
                'rows'  => $this->castRows($rows),
            ];
        });

        return response()->json([
            'success' => true,
            'users'   => $cachedData['rows'],
            'total'   => $cachedData['total'],
            'offset'  => $offset,
            'limit'   => $limit,
        ]);
    }

    private function castRows(array $rows): array
    {
        return array_map(function ($r) {
            $totalPoints = (int) $r->total_points;
            return [
                'user_id'              => (int) $r->user_id,
                'full_name'            => $r->full_name,
                'role'                 => $r->role ?? 'tourist',
                'avatar'               => $r->avatar ?? null,
                'municipality_name'    => $r->municipality_name ?? null,
                'last_activity_date'   => $r->last_activity_date ?: null,
                'total_points'         => $totalPoints,
                'completed_activities' => (int) $r->completed_activities,
                'spots_managed'        => (int) ($r->spots_managed ?? 0),
                'rank'                 => $totalPoints > 0 ? (int) $r->user_rank : null,
                'points_since'         => $r->points_since,
            ];
        }, $rows);
    }
}
