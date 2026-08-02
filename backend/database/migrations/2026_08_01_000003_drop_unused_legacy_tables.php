<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop 6 confirmed unused legacy tables.
     *
     * - quests                    : leftover gamification feature, quest_completions was already dropped
     * - vehicles                  : superseded by vehicle_types table
     * - email_logs                : no model/controller, created manually, never used
     * - email_sender_accounts     : legacy multi-sender design, replaced by Brevo API
     * - frontend_password_resets  : duplicate of password_reset_tokens
     * - password_reset_rate_limits: rate limiting handled by Laravel throttle middleware
     */
    public function up(): void
    {
        Schema::dropIfExists('quests');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_sender_accounts');
        Schema::dropIfExists('frontend_password_resets');
        Schema::dropIfExists('password_reset_rate_limits');
    }

    /**
     * These tables are confirmed unused — no rollback is provided.
     */
    public function down(): void
    {
        // Intentionally left blank — these tables are permanently removed.
    }
};
