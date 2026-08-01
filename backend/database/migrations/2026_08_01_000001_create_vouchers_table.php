<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('voucher_code')->unique();
                $table->string('voucher_name');
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->string('discount_type'); // percentage, fixed, free_entrance, bogo, free_souvenir, custom
                $table->decimal('discount_value', 10, 2)->nullable();
                $table->integer('required_points')->default(0);
                $table->integer('available_quantity')->default(0);
                $table->integer('redeemed_quantity')->default(0);
                $table->integer('remaining_quantity')->default(0);
                $table->unsignedBigInteger('municipality_id')->nullable();
                $table->string('partner_establishment')->nullable();
                $table->integer('maximum_redemption_per_user')->default(1);
                $table->dateTime('valid_from');
                $table->dateTime('expires_at');
                $table->text('terms_and_conditions')->nullable();
                $table->string('status')->default('active'); // active, inactive, archived
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('municipality_id')->references('id')->on('municipalities')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

                $table->index(['status', 'expires_at']);
                $table->index('municipality_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
