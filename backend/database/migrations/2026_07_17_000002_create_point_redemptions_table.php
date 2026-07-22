<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('type'); // pasalubong_discount, environmental_fee
            $table->integer('points_cost');
            $table->string('voucher_code')->unique();
            $table->string('status')->default('active'); // active, used, expired
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_redemptions');
    }
};
