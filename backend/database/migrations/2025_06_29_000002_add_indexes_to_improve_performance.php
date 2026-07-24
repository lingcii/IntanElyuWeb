<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Indexes for tourist_spots table
        try {
            Schema::table('tourist_spots', function (Blueprint $table) {
                $table->index(['status', 'municipality_id']);
                $table->index(['category']);
                $table->index(['classification_status']);
                $table->index(['visits']);
                $table->index(['rating']);
                $table->index(['created_at']);
            });
        } catch (\Throwable $e) {}

        // Indexes for analytics table
        try {
            if (Schema::hasColumn('analytics', 'date')) {
                Schema::table('analytics', function (Blueprint $table) {
                    $table->index(['date']);
                });
            } elseif (Schema::hasColumn('analytics', 'year')) {
                Schema::table('analytics', function (Blueprint $table) {
                    $table->index(['year', 'month']);
                    $table->index(['municipality_id', 'year', 'month']);
                });
            }
        } catch (\Throwable $e) {}

        // Indexes for users table
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['role', 'status']);
                $table->index(['status']);
            });
        } catch (\Throwable $e) {}

        // Indexes for alerts table
        try {
            Schema::table('alerts', function (Blueprint $table) {
                $table->index(['is_read', 'created_at']);
            });
        } catch (\Throwable $e) {}

        // Indexes for tourist_spot_images
        try {
            Schema::table('tourist_spot_images', function (Blueprint $table) {
                $table->index(['spot_id', 'is_primary']);
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::table('tourist_spots', function (Blueprint $table) {
            $table->dropIndex(['status', 'municipality_id']);
            $table->dropIndex(['category']);
            $table->dropIndex(['classification_status']);
            $table->dropIndex(['visits']);
            $table->dropIndex(['rating']);
            $table->dropIndex(['created_at']);
        });

        if (Schema::hasColumn('analytics', 'date')) {
            Schema::table('analytics', function (Blueprint $table) {
                $table->dropIndex(['date']);
            });
        } elseif (Schema::hasColumn('analytics', 'year')) {
            Schema::table('analytics', function (Blueprint $table) {
                $table->dropIndex(['year', 'month']);
                $table->dropIndex(['municipality_id', 'year', 'month']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropIndex(['status']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['is_read', 'created_at']);
        });

        Schema::table('tourist_spot_images', function (Blueprint $table) {
            $table->dropIndex(['spot_id', 'is_primary']);
        });
    }
};
