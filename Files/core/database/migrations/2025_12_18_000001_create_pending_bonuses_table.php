<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_id')->index();
            $table->string('bonus_type')->comment('direct/level_pair/pair/matching');
            $table->decimal('amount', 16, 8);
            $table->string('source_type')->comment('order/weekly_settlement');
            $table->string('source_id')->index();
            $table->string('accrued_week_key')->nullable()->index();
            $table->enum('status', ['pending', 'released', 'rejected'])->default('pending');
            $table->enum('release_mode', ['auto', 'manual'])->default('auto');
            $table->unsignedBigInteger('released_trx')->nullable();
            $table->timestamps();

            // 幂等键：同一来源同一收款人不重复
            $table->unique(['recipient_id', 'bonus_type', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_bonuses');
    }
};
