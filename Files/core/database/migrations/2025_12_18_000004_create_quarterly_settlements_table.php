<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarterly_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('quarter_key')->unique()->index();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->decimal('total_pv', 20, 8);
            $table->decimal('pool_stockist', 20, 8)->comment('1%消费商池');
            $table->decimal('pool_leader', 20, 8)->comment('3%领导人池');
            $table->integer('total_shares')->default(0)->comment('消费商池总份数');
            $table->decimal('total_score', 20, 8)->default(0)->comment('领导人池总积分');
            $table->decimal('unit_value_stockist', 20, 8)->comment('消费商池单位价值');
            $table->decimal('unit_value_leader', 20, 8)->comment('领导人池单位价值');
            $table->timestamp('finalized_at')->nullable();
            $table->json('config_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_settlements');
    }
};
