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
        if (Schema::hasTable('user_sessions')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('user_sessions', 'platform')) {
                    $table->string('platform')->nullable()->after('user_agent');
                }
                if (!Schema::hasColumn('user_sessions', 'last_activity')) {
                    $table->timestamp('last_activity')->nullable()->after('created_at');
                }
                if (!Schema::hasColumn('user_sessions', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('expires_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_sessions')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                $table->dropColumn(['platform', 'last_activity', 'is_active']);
            });
        }
    }
};
