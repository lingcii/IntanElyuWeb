<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    private const CACHE_KEY_STATS = 'activity_stats';

    /**
     * GET /api/{role}/activity-logs
     * Returns paginated activity logs with server-side filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->get('per_page', 10), 5), 100);
        $page    = max((int) $request->get('page', 1), 1);

        $query = ActivityLog::with('user:id,name,email,role,avatar,municipality_id')
            ->latest();

        // Filters
        if ($request->filled('action')) {
            $actions = is_array($request->action) ? $request->action : explode(',', $request->action);
            $query->whereIn('action', $actions);
        }

        if ($request->filled('module')) {
            $modules = is_array($request->module) ? $request->module : explode(',', $request->module);
            $query->whereIn('module', $modules);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('role')) {
            $query->where('user_role', $request->role);
        }

        if ($request->filled('municipality')) {
            $query->where('municipality', $request->municipality);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Date presets
        if ($request->filled('date_preset')) {
            switch ($request->date_preset) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', today()->subDay());
                    break;
                case 'last7':
                    $query->whereDate('created_at', '>=', today()->subDays(7));
                    break;
                case 'last30':
                    $query->whereDate('created_at', '>=', today()->subDays(30));
                    break;
            }
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'logs' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/{role}/activity-logs/stats
     * Returns summary statistics for the dashboard cards.
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember(self::CACHE_KEY_STATS, 30, function () {
            $today = today();

            $todayStats = DB::table('activity_logs')
                ->whereDate('created_at', $today)
                ->selectRaw("
                    COUNT(*) as logs_today,
                    COUNT(CASE WHEN action = 'Tourist Spot Approved' THEN 1 END) as approvals_today,
                    COUNT(CASE WHEN action = 'Tourist Spot Rejected' THEN 1 END) as rejections_today
                ")
                ->first();

            $activeUsers24h = DB::table('activity_logs')
                ->where('created_at', '>=', now()->subHours(24))
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            $totalLogs = DB::table('activity_logs')->count();

            return [
                'logs_today'         => (int) ($todayStats->logs_today ?? 0),
                'approvals_today'    => (int) ($todayStats->approvals_today ?? 0),
                'rejections_today'   => (int) ($todayStats->rejections_today ?? 0),
                'active_users_24h'   => (int) $activeUsers24h,
                'total_logs'         => (int) $totalLogs,
            ];
        });

        return response()->json($stats);
    }

    /**
     * GET /api/{role}/activity-logs/stream
     * Server-Sent Events (SSE) real-time stream with stats updates.
     */
    public function stream(Request $request)
    {
        $lastId = (int) $request->query('last_id', 0);

        return response()->stream(function () use ($lastId) {
            $startTime = time();
            $currentLastId = $lastId;
            $lastStatsPush = 0;

            // Send initial stats
            $stats = Cache::get(self::CACHE_KEY_STATS);
            if ($stats) {
                echo "event: stats\n";
                echo "data: " . json_encode($stats) . "\n\n";
            }

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $newLogs = ActivityLog::with('user:id,name,email,role,avatar,municipality_id')
                    ->where('id', '>', $currentLastId)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($newLogs->isNotEmpty()) {
                    foreach ($newLogs as $log) {
                        echo "id: {$log->id}\n";
                        echo "event: log\n";
                        echo "data: " . json_encode($log) . "\n\n";
                        $currentLastId = $log->id;
                    }
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Push stats every 5 seconds if changed
                if (time() - $lastStatsPush > 5) {
                    Cache::forget(self::CACHE_KEY_STATS);
                    $freshStats = $this->computeStats();
                    Cache::put(self::CACHE_KEY_STATS, $freshStats, 30);
                    echo "event: stats\n";
                    echo "data: " . json_encode($freshStats) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    $lastStatsPush = time();
                }

                sleep(1);

                if (time() - $startTime > 5) {
                    // Send retry hint before reconnecting
                    echo "retry: 3000\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function computeStats(): array
    {
        $today = today();

        return [
            'logs_today' => ActivityLog::whereDate('created_at', $today)->count(),
            'approvals_today' => ActivityLog::whereDate('created_at', $today)
                ->whereIn('action', ['Tourist Spot Approved'])->count(),
            'rejections_today' => ActivityLog::whereDate('created_at', $today)
                ->whereIn('action', ['Tourist Spot Rejected'])->count(),
            'active_users_24h' => ActivityLog::where('created_at', '>=', now()->subHours(24))
                ->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'total_logs' => ActivityLog::count(),
        ];
    }
}
