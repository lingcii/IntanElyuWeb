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
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // e.g. 'Public Vehicle', 'Private Vehicle'
            $table->string('name');     // e.g. 'TAXI', 'Tricycle', 'Car'
            $table->timestamps();

            $table->unique(['category', 'name'], 'vehicle_types_cat_name_unique');
            $table->index('category');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
