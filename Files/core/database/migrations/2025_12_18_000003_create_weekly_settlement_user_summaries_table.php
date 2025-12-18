<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_settlement_user_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('week_key')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('left_pv_initial', 20, 8)->default(0);
            $table->decimal('right_pv_initial', 20, 8)->default(0);
            $table->decimal('left_pv_end', 20, 8)->default(0);
            $table->decimal('right_pv_end', 20, 8)->default(0);
            $table->integer('pair_count')->default(0)->comment('对碰次数');
            $table->decimal('pair_theoretical', 20, 8)->default(0)->comment('对碰理论金额');
            $table->decimal('pair_capped_potential', 20, 8)->default(0)->comment('对碰封顶后理论');
            $table->decimal('pair_paid', 20, 8)->default(0)->comment('对碰实发');
            $table->decimal('matching_potential', 20, 8)->default(0)->comment('管理奖理论');
            $table->decimal('matching_paid', 20, 8)->default(0)->comment('管理奖实发');
            $table->decimal('cap_amount', 20, 8)->default(0)->comment('周封顶额度');
            $table->decimal('cap_used', 20, 8)->default(0)->comment('封顶使用额度');
            $table->decimal('k_factor', 10, 6)->comment('K值');
            $table->timestamps();

            $table->unique(['week_key', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_settlement_user_summaries');
    }
};
