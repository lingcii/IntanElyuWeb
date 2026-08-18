<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourist_spot_service_center', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tourist_spot_id');
            $table->unsignedBigInteger('service_center_id');
            $table->timestamps();

            $table->foreign('tourist_spot_id')->references('id')->on('tourist_spots')->onDelete('cascade');
            $table->foreign('service_center_id')->references('id')->on('service_centers')->onDelete('cascade');

            $table->unique(['tourist_spot_id', 'service_center_id'], 'spot_sc_unique');
            $table->index('service_center_id', 'sc_spot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourist_spot_service_center');
    }
};
