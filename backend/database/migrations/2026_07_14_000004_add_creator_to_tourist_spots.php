<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            /* Stores the ID of the user who originally created the spot.
               No FK constraint to avoid engine/type mismatch issues. */
            $table->unsignedInteger('created_by')->nullable()->after('approved_at');
            $table->string('creator_role', 50)->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'creator_role']);
        });
    }
};
