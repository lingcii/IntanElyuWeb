<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Technique 1: Indexing ---
        // These indexes target the heaviest queries in the system: leaderboard ranking,
        // dashboard trending, and check-in activity counts.

        $indexes = [
            // Covering index for leaderboard CTE: WHERE role='tourist' AND status='active' ORDER BY xp DESC
            'users' => [
                ['role', 'status', 'xp'],
                ['xp'],
            ],

            // Correlated subquery in CTE: COUNT(*) FROM itinerary_items WHERE is_visited=1
            'itinerary_items' => [
                ['itinerary_id', 'is_visited'],
                ['is_visited', 'itinerary_id'],
            ],

            // Dashboard trending: WHERE status='approved' ORDER BY visits DESC
            'tourist_spots' => [
                ['status', 'visits'],
            ],
        ];

        foreach ($indexes as $table => $cols) {
            foreach ($cols as $col) {
                $indexName = $table . '_' . implode('_', $col) . '_scaling_idx';
                try {
                    Schema::table($table, function (Blueprint $t) use ($col, $indexName) {
                        $t->index($col, $indexName);
                    });
                } catch (\Exception $e) {
                    // Index may already exist — safe to skip
                }
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'users' => [
                'users_role_status_xp_scaling_idx',
                'users_xp_scaling_idx',
            ],
            'itinerary_items' => [
                'itinerary_items_itinerary_id_is_visited_scaling_idx',
                'itinerary_items_is_visited_itinerary_id_scaling_idx',
            ],
            'tourist_spots' => [
                'tourist_spots_status_visits_scaling_idx',
            ],
        ];

        foreach ($indexes as $table => $idxNames) {
            foreach ($idxNames as $idxName) {
                try {
                    Schema::table($table, function (Blueprint $t) use ($idxName) {
                        $t->dropIndex($idxName);
                    });
                } catch (\Exception $e) {
                    // Index may not exist
                }
            }
        }
    }
};
