<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('points')->default(50);
            $table->timestamps();
        });

        // Seed default points for existing classifications
        $now = now();
        DB::table('classifications')->insert([
            ['name' => 'EXISTING', 'points' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'EMERGING', 'points' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'POTENTIAL', 'points' => 75, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
