<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshLeaderboard extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'leaderboard:refresh';

    /**
     * The console command description.
     */
    protected $description = 'Refresh the leaderboard_cache materialized view table with current rankings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Refreshing leaderboard cache...');

        $startTime = microtime(true);

        // Delete and re-insert in a transaction for atomicity (avoid truncate as it causes implicit commit in MySQL)
        DB::transaction(function () {
            DB::table('leaderboard_cache')->delete();

            DB::statement("
                INSERT INTO leaderboard_cache (user_id, full_name, total_points, completed_activities, `rank`, last_activity, points_since, refreshed_at)
                SELECT
                    u.id AS user_id,
                    CASE WHEN u.is_leaderboard_private = 1 THEN CONCAT('Explorer #', u.id) ELSE u.name END AS full_name,
                    COALESCE(u.xp, 0) AS total_points,
                    COALESCE(u.completed_activities, 0) AS completed_activities,
                    ROW_NUMBER() OVER (
                        ORDER BY
                            COALESCE(u.xp, 0) DESC,
                            COALESCE(u.completed_activities, 0) DESC,
                            u.created_at ASC
                    ) AS `rank`,
                    u.last_activity AS last_activity,
                    u.created_at AS points_since,
                    NOW() AS refreshed_at
                FROM users u
                WHERE u.role = 'tourist' AND u.status = 'active'
            ");
        });

        $elapsed = round((microtime(true) - $startTime) * 1000, 1);
        $count = DB::table('leaderboard_cache')->count();

        $this->info("Done! Cached {$count} ranked users in {$elapsed}ms.");

        return Command::SUCCESS;
    }
}
