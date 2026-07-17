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
        Schema::create('transportation_routes', function (Blueprint $table) {
            $table->id();
            $table->string('destination');
            $table->unsignedBigInteger('tourist_spot_id')->nullable();
            $table->unsignedBigInteger('fare_matrices_id')->nullable();
            $table->string('vehicle_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transportation_routes');
    }
};
