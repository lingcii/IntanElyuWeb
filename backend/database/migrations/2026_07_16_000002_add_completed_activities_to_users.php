<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Technique 5: Denormalization ---
        // Store completed_activities count directly on users table to avoid
        // expensive correlated subqueries in every leaderboard/rank query.

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('completed_activities')->default(0)->after('xp');
        });

        // Backfill existing data from itinerary_items
        DB::statement("
            UPDATE users u
            SET u.completed_activities = (
                SELECT COUNT(*)
                FROM itinerary_items ii
                JOIN itineraries i ON ii.itinerary_id = i.id
                WHERE i.user_id = u.id AND ii.is_visited = 1
            )
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('completed_activities');
        });
    }
};
