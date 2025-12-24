<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_settlements', 'carry_flash_at')) {
                $table->timestamp('carry_flash_at')->nullable()->after('finalized_at')->comment('PV结转执行时间');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_settlements', function (Blueprint $table) {
            $table->dropColumn('carry_flash_at');
        });
    }
};
