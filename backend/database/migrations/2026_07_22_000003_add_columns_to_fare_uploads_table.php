<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fare_uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('fare_uploads', 'file_type')) {
                $table->string('file_type')->nullable()->after('mime_type');
            }
            if (!Schema::hasColumn('fare_uploads', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
            if (!Schema::hasColumn('fare_uploads', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fare_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('fare_uploads', 'file_type')) {
                $table->dropColumn('file_type');
            }
            if (Schema::hasColumn('fare_uploads', 'error_message')) {
                $table->dropColumn('error_message');
            }
            if (Schema::hasColumn('fare_uploads', 'processed_at')) {
                $table->dropColumn('processed_at');
            }
        });
    }
};
