<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds proof validation tracking columns to itinerary_items.
     * The mobile app already stores proof_image and is_visited.
     * These new columns allow MTO users to approve or reject submitted proof images.
     */
    public function up(): void
    {
        Schema::table('itinerary_items', function (Blueprint $table) {
            // Proof validation status — separate from itineraries.status to avoid collision
            $table->string('proof_status', 20)->default('pending')->after('visited_at')
                  ->comment('pending | under_review | approved | rejected');

            // MTO user who reviewed the proof image
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('proof_status');

            // When the review action was taken
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            // Required when proof_status = rejected
            $table->text('rejection_reason')->nullable()->after('reviewed_at');

            // Index for fast filtering by status in the validation module
            $table->index('proof_status', 'itinerary_items_proof_status_idx');

            // FK to the reviewing MTO user
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itinerary_items', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex('itinerary_items_proof_status_idx');
            $table->dropColumn(['proof_status', 'reviewed_by', 'reviewed_at', 'rejection_reason']);
        });
    }
};
