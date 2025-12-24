<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 将 data_values 字段从 text 改为 longText 以支持大型富文本内容
        if (Schema::hasTable('frontends')) {
            DB::statement("ALTER TABLE frontends MODIFY COLUMN data_values LONGTEXT DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontends')) {
            DB::statement("ALTER TABLE frontends MODIFY COLUMN data_values TEXT DEFAULT NULL");
        }
    }
};
