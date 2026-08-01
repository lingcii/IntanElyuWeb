<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('voucher_id');
                $table->unsignedBigInteger('user_id');
                $table->string('redemption_code')->unique();
                $table->integer('points_used');
                $table->string('status')->default('pending'); // pending, claimed, completed, cancelled, expired
                $table->timestamp('redeemed_at')->useCurrent();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();

                $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                $table->index(['voucher_id', 'user_id']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
