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
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id(); // BigInt Unsigned
            $table->integer('user_id');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('title');
            $table->date('trip_date')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('status')->default('pending'); // pending, completed
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itineraries');
    }
};
