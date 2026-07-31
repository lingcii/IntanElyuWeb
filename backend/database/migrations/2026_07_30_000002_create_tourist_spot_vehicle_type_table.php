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
        Schema::create('tourist_spot_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_spot_id')
                  ->constrained('tourist_spots')
                  ->onDelete('cascade');
            $table->foreignId('vehicle_type_id')
                  ->constrained('vehicle_types')
                  ->onDelete('cascade');

            $table->unique(['tourist_spot_id', 'vehicle_type_id'], 'ts_vt_unique');
            $table->index('tourist_spot_id');
            $table->index('vehicle_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourist_spot_vehicle_type');
    }
};
