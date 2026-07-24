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
        Schema::create('tourist_spots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('municipality_id')->constrained();
            $table->string('category');
            $table->decimal('entrance_fee', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('photo_url')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->boolean('is_maintenance')->default(false);
            $table->string('status')->default('approved');
            $table->string('classification_status')->nullable();
            $table->integer('visits')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tourist_spots');
    }
};
