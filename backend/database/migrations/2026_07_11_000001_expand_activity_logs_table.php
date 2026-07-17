<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('module', 100)->nullable()->after('ip_address');
            $table->text('description')->nullable()->after('module');
            $table->string('user_name')->nullable()->after('description');
            $table->string('user_role', 50)->nullable()->after('user_name');
            $table->string('municipality', 100)->nullable()->after('user_role');
            $table->text('user_agent')->nullable()->after('municipality');
            $table->string('device', 50)->nullable()->after('user_agent');
            $table->string('browser', 50)->nullable()->after('device');
            $table->string('os', 50)->nullable()->after('browser');
            $table->json('old_value')->nullable()->after('os');
            $table->json('new_value')->nullable()->after('old_value');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('action');
            $table->index('module');
            $table->index('created_at');
            $table->index('user_id');
            $table->index('user_role');
            $table->index('municipality');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropIndex(['module']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_role']);
            $table->dropIndex(['municipality']);

            $table->dropColumn([
                'module', 'description', 'user_name', 'user_role',
                'municipality', 'user_agent', 'device', 'browser', 'os',
                'old_value', 'new_value',
            ]);
        });
    }
};
