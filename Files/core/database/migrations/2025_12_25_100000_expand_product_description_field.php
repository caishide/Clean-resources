<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 将 products 表的 description 字段从 string 改为 text，
     * 以支持从 Word 文档复制过来的长内容。
     */
    public function up(): void
    {
        // 检查字段是否存在且类型为 string
        if (Schema::hasColumn('products', 'description')) {
            // 使用原始 SQL 修改字段类型
            \DB::statement("ALTER TABLE products MODIFY COLUMN description LONGTEXT DEFAULT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚: 将字段改回 string(255)
        if (Schema::hasColumn('products', 'description')) {
            \DB::statement("ALTER TABLE products MODIFY COLUMN description VARCHAR(255) DEFAULT NULL");
        }
    }
};
