<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->decimal('environmental_fee', 10, 2)->default(0)->after('entrance_fee');
            $table->json('fee_types')->nullable()->after('environmental_fee');
        });
    }

    public function down(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->dropColumn(['environmental_fee', 'fee_types']);
        });
    }
};
