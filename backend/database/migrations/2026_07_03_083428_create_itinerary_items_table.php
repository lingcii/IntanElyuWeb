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
        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('itinerary_id'); // Match itineraries.id
            $table->unsignedBigInteger('tourist_spot_id'); // Match tourist_spots.id
            
            $table->foreign('itinerary_id')->references('id')->on('itineraries')->onDelete('cascade');
            $table->foreign('tourist_spot_id')->references('id')->on('tourist_spots')->onDelete('cascade');
            
            $table->boolean('is_visited')->default(false);
            $table->string('proof_image')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_items');
    }
};
