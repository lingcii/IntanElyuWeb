<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update users_status_check constraint to include 'pending' and 'archived'.
     */
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE users DROP CHECK users_status_check");
        } catch (\Exception $e) {}

        try {
            DB::statement("ALTER TABLE users DROP CONSTRAINT users_status_check");
        } catch (\Exception $e) {}

        try {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'pending', 'archived', 'banned'))");
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE users DROP CHECK users_status_check");
        } catch (\Exception $e) {}

        try {
            DB::statement("ALTER TABLE users DROP CONSTRAINT users_status_check");
        } catch (\Exception $e) {}

        try {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'banned'))");
        } catch (\Exception $e) {}
    }
};
