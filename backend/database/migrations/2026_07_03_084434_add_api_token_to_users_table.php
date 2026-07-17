<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 80)->nullable()->unique()->after('password');
            $table->integer('xp')->default(0)->after('api_token');
            $table->integer('level')->default(1)->after('xp');
            $table->string('avatar')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['api_token', 'xp', 'level', 'avatar']);
        });
    }
};
