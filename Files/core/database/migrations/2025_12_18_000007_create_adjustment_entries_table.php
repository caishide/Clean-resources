<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->enum('asset_type', ['wallet', 'pv', 'points']);
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount', 20, 8)->comment('调整量（可为负）');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->comment('被冲正的原始流水ID');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_entries');
    }
};
