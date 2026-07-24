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
        Schema::table('tourist_spots', function (Blueprint $table) {
            if (!Schema::hasColumn('tourist_spots', 'barangay')) {
                $table->string('barangay', 255)->nullable()->after('municipality_id');
            }
            if (!Schema::hasColumn('tourist_spots', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->dropColumn(['barangay', 'updated_at']);
        });
    }
};
