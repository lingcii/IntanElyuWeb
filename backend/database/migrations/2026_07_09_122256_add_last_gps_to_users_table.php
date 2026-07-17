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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('last_gps_lat', 10, 8)->nullable()->after('level');
            $table->decimal('last_gps_lng', 11, 8)->nullable()->after('last_gps_lat');
            $table->timestamp('last_gps_ping_at')->nullable()->after('last_gps_lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_gps_lat', 'last_gps_lng', 'last_gps_ping_at']);
        });
    }
};
