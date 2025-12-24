<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pv_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('pv_ledger', 'details')) {
                $table->text('details')->nullable()->after('source_id')->comment('备注信息');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pv_ledger', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
