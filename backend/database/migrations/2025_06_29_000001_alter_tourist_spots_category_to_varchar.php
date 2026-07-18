<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change the `category` column on tourist_spots from ENUM to VARCHAR(255).
 * This allows storing comma-separated multi-category values like "Beach,Mountain".
 * Existing single-value rows are preserved unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tourist_spots ALTER COLUMN category TYPE VARCHAR(255), ALTER COLUMN category SET NOT NULL, ALTER COLUMN category SET DEFAULT 'Other'");
        } else {
            DB::statement("ALTER TABLE tourist_spots MODIFY COLUMN category VARCHAR(255) NOT NULL DEFAULT 'Other'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement("CREATE TYPE tourist_spots_category_enum AS ENUM('Beach','Mountain','Historical','Waterfalls','Adventure','Farm','Religious','Other')");
            } catch (\Exception $e) {
                // Type might already exist
            }
            DB::statement("ALTER TABLE tourist_spots ALTER COLUMN category TYPE tourist_spots_category_enum USING category::tourist_spots_category_enum, ALTER COLUMN category SET DEFAULT 'Other'");
        } else {
            DB::statement("ALTER TABLE tourist_spots MODIFY COLUMN category ENUM('Beach','Mountain','Historical','Waterfalls','Adventure','Farm','Religious','Other') NOT NULL DEFAULT 'Other'");
        }
    }
};
