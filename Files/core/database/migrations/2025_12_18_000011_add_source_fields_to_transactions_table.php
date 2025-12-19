<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'source_type')) {
                    $table->string('source_type', 30)->nullable()->after('remark');
                }
                if (!Schema::hasColumn('transactions', 'source_id')) {
                    $table->string('source_id', 50)->nullable()->after('source_type');
                }
                if (!Schema::hasColumn('transactions', 'reversal_of_id')) {
                    $table->unsignedBigInteger('reversal_of_id')->nullable()->after('source_id');
                }
                if (!Schema::hasColumn('transactions', 'adjustment_batch_id')) {
                    $table->unsignedBigInteger('adjustment_batch_id')->nullable()->after('reversal_of_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $cols = ['source_type', 'source_id', 'reversal_of_id', 'adjustment_batch_id'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('transactions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
