<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 100);
            $table->enum('status', ['online', 'warning', 'offline'])->default('online');
            $table->string('uptime', 50)->default('99.9%');
            $table->timestamp('last_checked')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_statuses');
    }
};
