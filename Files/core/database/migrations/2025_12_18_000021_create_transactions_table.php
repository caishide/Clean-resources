<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('trx', 40)->nullable();
                $table->string('trx_type', 2);
                $table->decimal('amount', 20, 8);
                $table->decimal('charge', 20, 8)->default(0);
                $table->decimal('post_balance', 20, 8)->default(0);
                $table->string('remark')->nullable();
                $table->string('source_type', 30)->nullable();
                $table->string('source_id', 50)->nullable();
                $table->unsignedBigInteger('reversal_of_id')->nullable();
                $table->unsignedBigInteger('adjustment_batch_id')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
