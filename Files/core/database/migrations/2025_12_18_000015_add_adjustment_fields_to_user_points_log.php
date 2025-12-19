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
                if (!Schema::hasColumn('user_points_log', 'adjustment_batch_id')) {
                    $table->unsignedBigInteger('adjustment_batch_id')->nullable()->after('description')->index();
                }
                if (!Schema::hasColumn('user_points_log', 'reversal_of_id')) {
                    $table->unsignedBigInteger('reversal_of_id')->nullable()->after('adjustment_batch_id')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_points_log')) {
            Schema::table('user_points_log', function (Blueprint $table) {
                foreach (['adjustment_batch_id', 'reversal_of_id'] as $col) {
                    if (Schema::hasColumn('user_points_log', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
