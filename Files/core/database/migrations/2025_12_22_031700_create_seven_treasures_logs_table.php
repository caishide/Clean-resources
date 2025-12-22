<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seven_treasures_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('from_rank_code', 30)->nullable()->comment('原职级代码');
            $table->string('to_rank_code', 30)->comment('新职级代码');
            $table->string('to_rank_name', 50)->comment('新职级名称');
            $table->decimal('multiplier', 6, 2)->comment('分红系数');
            $table->enum('promotion_type', ['auto', 'manual'])->default('auto')->comment('晋升类型');
            $table->string('promoted_by', 50)->nullable()->comment('晋升执行人（手动晋升时）');
            $table->text('reason')->nullable()->comment('晋升原因');
            $table->json('requirements_snapshot')->nullable()->comment('晋升条件快照');
            $table->timestamp('promoted_at')->comment('晋升时间');
            $table->timestamps();

            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // 索引
            $table->index(['user_id', 'promoted_at']);
            $table->index('to_rank_code');
            $table->index('promotion_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seven_treasures_logs');
    }
};