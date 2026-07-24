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
        Schema::table('analytics', function (Blueprint $table) {
            if (!Schema::hasColumn('analytics', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('tourist_spot_id');
            }
            if (!Schema::hasColumn('analytics', 'month')) {
                $table->unsignedTinyInteger('month')->nullable()->after('year');
            }
            if (!Schema::hasColumn('analytics', 'visits')) {
                $table->unsignedInteger('visits')->default(0)->after('month');
            }
            if (!Schema::hasColumn('analytics', 'transport_car')) {
                $table->unsignedInteger('transport_car')->default(0)->after('visits');
            }
            if (!Schema::hasColumn('analytics', 'transport_bus')) {
                $table->unsignedInteger('transport_bus')->default(0)->after('transport_car');
            }
            if (!Schema::hasColumn('analytics', 'transport_van')) {
                $table->unsignedInteger('transport_van')->default(0)->after('transport_bus');
            }
            if (!Schema::hasColumn('analytics', 'transport_other')) {
                $table->unsignedInteger('transport_other')->default(0)->after('transport_van');
            }
        });

        // Make older columns nullable if necessary
        Schema::table('analytics', function (Blueprint $table) {
            if (Schema::hasColumn('analytics', 'metric')) {
                $table->string('metric')->nullable()->change();
            }
            if (Schema::hasColumn('analytics', 'value')) {
                $table->decimal('value', 12, 2)->nullable()->change();
            }
            if (Schema::hasColumn('analytics', 'date')) {
                $table->date('date')->nullable()->change();
            }
        });

        // Add indexes for performance
        try {
            Schema::table('analytics', function (Blueprint $table) {
                $table->index(['year', 'month'], 'idx_analytics_year_month');
                $table->index(['municipality_id', 'year', 'month'], 'idx_analytics_muni_year_month');
            });
        } catch (\Throwable $e) {
            // Indexes might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_analytics_year_month');
                $table->dropIndex('idx_analytics_muni_year_month');
            } catch (\Throwable $e) {}

            $columnsToDrop = array_filter([
                'year', 'month', 'visits', 'transport_car', 'transport_bus', 'transport_van', 'transport_other'
            ], fn($col) => Schema::hasColumn('analytics', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
