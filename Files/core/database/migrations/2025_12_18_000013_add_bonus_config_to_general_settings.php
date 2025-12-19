<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('general_settings', 'bonus_config')) {
                    $table->json('bonus_config')->nullable()->after('available_version');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (Schema::hasColumn('general_settings', 'bonus_config')) {
                    $table->dropColumn('bonus_config');
                }
            });
        }
    }
};
