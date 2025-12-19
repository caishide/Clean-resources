<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_points_log')) {
            Schema::table('user_points_log', function (Blueprint $table) {
                $table->string('source_type', 20)->change();
            });
        }
    }

    public function down(): void
    {
        // no rollback
    }
};
