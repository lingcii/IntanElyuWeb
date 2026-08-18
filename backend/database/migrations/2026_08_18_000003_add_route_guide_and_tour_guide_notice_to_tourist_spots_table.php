<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->text('route_guide')->nullable()->after('description');
            $table->text('tour_guide_notice')->nullable()->after('route_guide');
        });
    }

    public function down(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->dropColumn(['route_guide', 'tour_guide_notice']);
        });
    }
};
