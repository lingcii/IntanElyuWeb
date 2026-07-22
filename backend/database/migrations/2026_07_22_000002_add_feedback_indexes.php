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
        Schema::table('site_feedbacks', function (Blueprint $table) {
            $table->index(['tourist_spot_id', 'rating']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_feedbacks', function (Blueprint $table) {
            $table->dropIndex(['tourist_spot_id', 'rating']);
            $table->dropIndex(['created_at']);
        });
    }
};
