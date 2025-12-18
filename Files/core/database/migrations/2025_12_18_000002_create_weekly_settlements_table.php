<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('week_key')->unique()->index();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->decimal('total_pv', 20, 8);
            $table->decimal('fixed_sales', 20, 8)->comment('直推+层碰（已发+应计预留）');
            $table->decimal('global_reserve', 20, 8)->comment('功德池4%');
            $table->decimal('variable_potential', 20, 8)->comment('对碰封顶后理论+管理理论（未乘K）');
            $table->decimal('k_factor', 10, 6)->comment('K值');
            $table->timestamp('finalized_at')->nullable();
            $table->json('config_snapshot')->nullable()->comment('配置快照（比例/封顶等）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_settlements');
    }
};
