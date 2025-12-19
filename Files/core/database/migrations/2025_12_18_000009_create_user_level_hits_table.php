<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_level_hits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->integer('level')->comment('层级');
            $table->tinyInteger('position')->comment('1=Left, 2=Right');
            $table->decimal('amount', 16, 8)->comment('该层该侧累计PV');
            $table->timestamp('first_hit_at')->comment('首次点亮时间');
            $table->string('order_trx')->nullable()->comment('首次点亮来源订单');
            $table->decimal('bonus_amount', 16, 8)->default(0)->comment('层碰奖金额快照');
            $table->tinyInteger('rewarded')->default(0)->comment('0=未发放,1=已发放');
            $table->timestamps();

            // 幂等键：同一用户同一层同一侧仅一条记录
            $table->unique(['user_id', 'level', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_level_hits');
    }
};
