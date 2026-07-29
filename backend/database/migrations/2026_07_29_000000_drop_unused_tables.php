<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('ar_checkins');
        Schema::dropIfExists('gamification_challenges');
        Schema::dropIfExists('point_redemptions');
        Schema::dropIfExists('quest_completions');
        Schema::dropIfExists('transportation_routes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Unused tables dropped permanently
    }
};
