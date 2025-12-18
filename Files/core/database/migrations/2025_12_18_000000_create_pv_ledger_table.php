<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pv_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('from_user_id')->nullable()->index();
            $table->tinyInteger('position')->comment('1=Left, 2=Right');
            $table->integer('level');
            $table->decimal('amount', 16, 8);
            $table->string('trx_type', 2)->comment('+ or -');
            $table->string('source_type', 30)->comment('order/weekly_settlement/adjustment');
            $table->string('source_id', 50)->index();
            $table->unsignedBigInteger('adjustment_batch_id')->nullable()->index();
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
            $table->timestamps();

            // 幂等键：同一来源不重复入账
            $table->unique(['source_type', 'source_id', 'user_id', 'position', 'trx_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pv_ledger');
    }
};
