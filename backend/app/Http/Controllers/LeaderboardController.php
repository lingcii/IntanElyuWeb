<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    private function rankedCte(): string
    {
        return "
            WITH ranked AS (
                SELECT
                    u.id                                              AS user_id,
                    u.name                                            AS full_name,
                    u.role                                            AS role,
                    u.avatar                                          AS avatar,
                    m.name                                            AS municipality_name,
                    u.last_activity                                   AS last_activity_date,
                    COALESCE(up.total_points, 0)                      AS total_points,
                    COALESCE(up.completed_activities, 0)              AS completed_activities,
                    COALESCE(up.points_since, u.created_at)           AS points_since,
                    0                                                 AS spots_managed,
                    ROW_NUMBER() OVER (
                        ORDER BY
                            COALESCE(up.total_points, 0)               DESC,
                            COALESCE(up.completed_activities, 0)        DESC,
                            COALESCE(up.points_since, u.created_at)     ASC
                    ) AS user_rank
                FROM users u
                LEFT JOIN user_points up ON up.user_id = u.id
                LEFT JOIN municipalities m ON m.id = u.municipality_id
                WHERE u.role = 'tourist' AND u.status = 'active'
            )
        ";
    }

    public function top3(): JsonResponse
    {
        $rows = \Illuminate\Support\Facades\Cache::remember('leaderboard:top3', 60, function () {
            $rows = DB::select($this->rankedCte() . 'SELECT * FROM ranked WHERE user_rank <= 3 ORDER BY user_rank ASC');
            return $this->castRows($rows);
        });

        return response()->json(['success' => true, 'top3' => $rows]);
    }

    public function kpis(): JsonResponse
    {
        $kpis = \Illuminate\Support\Facades\Cache::remember('leaderboard:kpis', 60, function () {
            $kpi = DB::selectOne("
                SELECT
                    COUNT(u.id)                               AS total_users,
                    COALESCE(SUM(up.total_points), 0)         AS grand_points,
                    COALESCE(SUM(up.completed_activities), 0) AS total_activities,
                    COALESCE(MAX(up.total_points), 0)         AS highest_points
                FROM users u
                LEFT JOIN user_points up ON up.user_id = u.id
                WHERE u.role = 'tourist' AND u.status = 'active'
            ");

            return [
                'total_users'      => (int) $kpi->total_users,
                'grand_points'     => (int) $kpi->grand_points,
                'total_activities' => (int) $kpi->total_activities,
                'highest_points'   => (int) $kpi->highest_points,
            ];
        });

        return response()->json(['success' => true, 'kpis' => $kpis]);
    }

    public function index(Request $request): JsonResponse
    {
        $search  = $request->get('search', '');
        $sortBy  = $request->get('sort', 'points_desc');
        $show    = $request->get('show', '100');

        $orderMap = [
            'points_desc'     => 'total_points DESC, completed_activities DESC, points_since ASC',
            'points_asc'      => 'total_points ASC, completed_activities ASC, points_since DESC',
            'activities_desc' => 'completed_activities DESC, total_points DESC, points_since ASC',
            'name_asc'        => 'full_name ASC',
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

        if ($request->filled('search')) {
            $search = $request->get('search');
            $castType = DB::getDriverName() === 'pgsql' ? 'VARCHAR' : 'CHAR';
            $whereClause = "WHERE full_name LIKE ? OR CAST(user_id AS {$castType}) LIKE ?";
            $params      = ["%{$search}%", "%{$search}%"];
        }

        $cacheKey = "leaderboard:index:{$search}:{$sortBy}:{$show}:{$offset}";

        $cachedData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($whereClause, $params, $orderSql, $limit, $offset, $show) {
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
            return [
                'user_id'              => (int) $r->user_id,
                'full_name'            => $r->full_name,
                'role'                 => $r->role ?? 'tourist',
                'avatar'               => $r->avatar ?? null,
                'municipality_name'    => $r->municipality_name ?? null,
                'last_activity_date'   => $r->last_activity_date ?: null,
                'total_points'         => (int) $r->total_points,
                'completed_activities' => (int) $r->completed_activities,
                'spots_managed'        => (int) ($r->spots_managed ?? 0),
                'rank'                 => (int) $r->user_rank,
                'points_since'         => $r->points_since,
            ];
        }, $rows);
    }
}
