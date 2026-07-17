<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('fare_uploads', 'mime_type')) {
            Schema::table('fare_uploads', function (Blueprint $table) {
                $table->string('mime_type')->nullable()->after('file_size');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fare_uploads', 'mime_type')) {
            Schema::table('fare_uploads', function (Blueprint $table) {
                $table->dropColumn('mime_type');
            });
        }
    }
};
