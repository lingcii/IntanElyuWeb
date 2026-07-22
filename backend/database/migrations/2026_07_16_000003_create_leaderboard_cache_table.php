<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Technique 6: Materialized Views ---
        // Pre-computed leaderboard rankings table, refreshed every 5 minutes
        // by the leaderboard:refresh artisan command. This eliminates the
        // expensive CTE + ROW_NUMBER() computation on every leaderboard request.

        Schema::create('leaderboard_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('full_name');
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('completed_activities')->default(0);
            $table->unsignedInteger('rank')->index();
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('points_since')->nullable();
            $table->timestamp('refreshed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_cache');
    }
};
