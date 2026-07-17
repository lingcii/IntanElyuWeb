<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fare_uploads', 'file_type')) {
            DB::statement("UPDATE fare_uploads SET mime_type = file_type WHERE (mime_type IS NULL OR mime_type = '') AND file_type IS NOT NULL");

            Schema::table('fare_uploads', function (Blueprint $table) {
                $table->string('file_type')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fare_uploads', 'file_type')) {
            Schema::table('fare_uploads', function (Blueprint $table) {
                $table->string('file_type')->nullable(false)->change();
            });
        }
    }
};
