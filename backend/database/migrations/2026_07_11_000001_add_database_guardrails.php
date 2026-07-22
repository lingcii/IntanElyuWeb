<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== 1. Missing foreign keys on transportation_routes =====
        if (Schema::hasColumn('transportation_routes', 'tourist_spot_id')) {
            try {
                Schema::table('transportation_routes', function (Blueprint $table) {
                    $table->foreign('tourist_spot_id')->references('id')->on('tourist_spots')->onDelete('set null');
                });
            } catch (\Exception $e) {}
        }
        if (Schema::hasColumn('transportation_routes', 'fare_matrices_id')) {
            try {
                Schema::table('transportation_routes', function (Blueprint $table) {
                    $table->foreign('fare_matrices_id')->references('id')->on('fare_matrices')->onDelete('set null');
                });
            } catch (\Exception $e) {}
        }

        // ===== 2. Unique constraint on favorites to prevent duplicate saves =====
        try {
            Schema::table('favorites', function (Blueprint $table) {
                $table->unique(['user_id', 'tourist_spot_id'], 'favorites_user_spot_unique');
            });
        } catch (\Exception $e) {}

        // ===== 3. Unique constraint on itinerary_items to prevent duplicate spots in one itinerary =====
        try {
            Schema::table('itinerary_items', function (Blueprint $table) {
                $table->unique(['itinerary_id', 'tourist_spot_id'], 'itinerary_items_spot_unique');
            });
        } catch (\Exception $e) {}

        // ===== 4. Check constraints on status/category columns =====
        // itineraries.status: only 'pending' or 'completed'
        try {
            DB::statement("ALTER TABLE itineraries ADD CONSTRAINT itineraries_status_check CHECK (status IN ('pending', 'completed'))");
        } catch (\Exception $e) {}

        // users.status: only 'active', 'inactive', or 'banned'
        try {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'banned'))");
        } catch (\Exception $e) {}

        // users.role: tourist, pitco, lupto, municipal, and null/empty values (no admin role)
        try {
            DB::statement("ALTER TABLE users DROP CONSTRAINT users_role_check");
        } catch (\Exception $e) {}
        try {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('tourist', 'pitco', 'lupto', 'municipal', '') OR role IS NULL)");
        } catch (\Exception $e) {}

        // tourist_spots.status: moderation status ('pending', 'approved', 'rejected')
        try {
            DB::statement("ALTER TABLE tourist_spots ADD CONSTRAINT tourist_spots_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        } catch (\Exception $e) {}

        // merch_reservations.status: only 'pending', 'claimed', 'cancelled'
        try {
            DB::statement("ALTER TABLE merch_reservations ADD CONSTRAINT merch_reservations_status_check CHECK (status IN ('pending', 'claimed', 'cancelled'))");
        } catch (\Exception $e) {}

        // ===== 5. Indexes for faster queries on commonly filtered/joined columns =====
        try {
            Schema::table('itineraries', function (Blueprint $table) {
                $table->index('user_id', 'itineraries_user_id_index');
                $table->index('status', 'itineraries_status_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('favorites', function (Blueprint $table) {
                $table->index('user_id', 'favorites_user_id_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('merch_reservations', function (Blueprint $table) {
                $table->index('user_id', 'merch_reservations_user_id_index');
                $table->index('status', 'merch_reservations_status_index');
                $table->index('merchandise_id', 'merch_reservations_merchandise_id_index');
            });
        } catch (\Exception $e) {}
    }

    public function down(): void
    {
        // Drop check constraints
        try { DB::statement("ALTER TABLE itineraries DROP CHECK itineraries_status_check"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE users DROP CHECK users_status_check"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE users DROP CHECK users_role_check"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE tourist_spots DROP CHECK tourist_spots_status_check"); } catch (\Exception $e) {}
        try { DB::statement("ALTER TABLE merch_reservations DROP CHECK merch_reservations_status_check"); } catch (\Exception $e) {}

        // Drop unique constraints
        try {
            Schema::table('favorites', function (Blueprint $table) {
                $table->dropUnique('favorites_user_spot_unique');
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('itinerary_items', function (Blueprint $table) {
                $table->dropUnique('itinerary_items_spot_unique');
            });
        } catch (\Exception $e) {}

        // Drop foreign keys
        try {
            Schema::table('transportation_routes', function (Blueprint $table) {
                $table->dropForeign(['tourist_spot_id']);
                $table->dropForeign(['fare_matrices_id']);
            });
        } catch (\Exception $e) {}

        // Drop indexes
        try {
            Schema::table('itineraries', function (Blueprint $table) {
                $table->dropIndex('itineraries_user_id_index');
                $table->dropIndex('itineraries_status_index');
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('favorites', function (Blueprint $table) {
                $table->dropIndex('favorites_user_id_index');
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('merch_reservations', function (Blueprint $table) {
                $table->dropIndex('merch_reservations_user_id_index');
                $table->dropIndex('merch_reservations_status_index');
                $table->dropIndex('merch_reservations_merchandise_id_index');
            });
        } catch (\Exception $e) {}
    }
};
