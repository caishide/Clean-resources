<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_points_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('source_type', ['PURCHASE', 'DIRECT', 'TEAM', 'DAILY'])->comment('莲子来源类型');
            $table->string('source_id')->nullable()->index()->comment('关联的订单ID或week_key');
            $table->decimal('points', 20, 8)->comment('莲子数量');
            $table->string('description')->nullable();
            $table->timestamps();

            // 幂等键：同一来源不重复入账
            $table->unique(['user_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_points_log');
    }
};
