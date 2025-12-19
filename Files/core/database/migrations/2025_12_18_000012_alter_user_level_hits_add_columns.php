<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_level_hits')) {
            Schema::table('user_level_hits', function (Blueprint $table) {
                if (!Schema::hasColumn('user_level_hits', 'order_trx')) {
                    $table->string('order_trx')->nullable()->after('first_hit_at');
                }
                if (!Schema::hasColumn('user_level_hits', 'bonus_amount')) {
                    $table->decimal('bonus_amount', 16, 8)->default(0)->after('order_trx');
                }
                if (!Schema::hasColumn('user_level_hits', 'rewarded')) {
                    $table->tinyInteger('rewarded')->default(0)->after('bonus_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_level_hits')) {
            Schema::table('user_level_hits', function (Blueprint $table) {
                foreach (['order_trx', 'bonus_amount', 'rewarded'] as $col) {
                    if (Schema::hasColumn('user_level_hits', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
