<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Route columns handled by 2026_07_07_194720_add_route_type_to_itineraries_table.
     */
    public function up(): void
    {
        // Columns already added by preceding migration
    }

    public function down(): void
    {
        // Handled by 2026_07_07_194720
    }
};
