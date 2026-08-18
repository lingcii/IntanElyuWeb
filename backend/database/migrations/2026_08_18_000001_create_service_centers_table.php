<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('type', 100); // predefined type
            $table->string('custom_type', 100)->nullable(); // filled when type = 'Other'
            $table->string('contact_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive
            $table->unsignedBigInteger('municipality_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('municipality_id')->references('id')->on('municipalities')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index('municipality_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_centers');
    }
};
