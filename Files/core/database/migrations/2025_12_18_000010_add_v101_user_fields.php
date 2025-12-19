<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'rank_level')) {
                    $table->tinyInteger('rank_level')->default(0)->comment('0=未激活,1=初级,2=中级,3=高级');
                }
                if (!Schema::hasColumn('users', 'personal_purchase_count')) {
                    $table->integer('personal_purchase_count')->default(0)->comment('个人累计请购数量');
                }
                if (!Schema::hasColumn('users', 'last_activity_date')) {
                    $table->date('last_activity_date')->nullable()->comment('季度活跃判定');
                }
                if (!Schema::hasColumn('users', 'leader_rank_code')) {
                    $table->string('leader_rank_code', 30)->nullable()->comment('领导人职级代码');
                }
                if (!Schema::hasColumn('users', 'leader_rank_multiplier')) {
                    $table->decimal('leader_rank_multiplier', 6, 2)->default(0)->comment('领导人分红系数');
                }
            });
        }

        if (Schema::hasTable('user_extras')) {
            Schema::table('user_extras', function (Blueprint $table) {
                if (!Schema::hasColumn('user_extras', 'points')) {
                    $table->decimal('points', 20, 8)->default(0)->comment('莲子积分余额')->after('bv_right');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = ['rank_level', 'personal_purchase_count', 'last_activity_date', 'leader_rank_code', 'leader_rank_multiplier'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('user_extras')) {
            Schema::table('user_extras', function (Blueprint $table) {
                if (Schema::hasColumn('user_extras', 'points')) {
                    $table->dropColumn('points');
                }
            });
        }
    }
};
