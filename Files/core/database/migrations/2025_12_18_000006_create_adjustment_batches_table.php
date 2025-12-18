<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key')->unique()->index();
            $table->enum('reason_type', ['refund_before_finalize', 'refund_after_finalize', 'manual_correction']);
            $table->enum('reference_type', ['order', 'weekly_settlement', 'quarterly_settlement']);
            $table->string('reference_id');
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->json('snapshot')->comment('调整详情快照');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_batches');
    }
};
