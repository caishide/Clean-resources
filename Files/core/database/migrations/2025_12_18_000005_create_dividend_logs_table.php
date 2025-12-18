<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividend_logs', function (Blueprint $table) {
            $table->id();
            $table->string('quarter_key')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('pool_type', ['stockist', 'leader'])->comment('stockist=1%消费商池, leader=3%领导人池');
            $table->integer('shares')->nullable()->comment('消费商池份数');
            $table->decimal('score', 20, 8)->nullable()->comment('领导人池积分');
            $table->decimal('dividend_amount', 20, 8)->default(0)->comment('分红金额');
            $table->enum('status', ['paid', 'skipped'])->comment('paid=已发, skipped=未达标未发');
            $table->string('reason')->nullable()->comment('跳过原因');
            $table->unsignedBigInteger('trx_id')->nullable()->comment('交易流水ID');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_logs');
    }
};
