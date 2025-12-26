<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 将 products 表的 description 字段从 string 改为 LONGTEXT，
     * 以支持从 Word 文档复制过来的长内容。
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'description')) {
            $driver = DB::getDriverName();
            $columnType = DB::getSchemaBuilder()->getColumnType('products', 'description');

            // 如果已经是 longtext/text 类型，跳过
            if (in_array($columnType, ['text', 'longtext'])) {
                return;
            }

            if ($driver === 'sqlite') {
                // SQLite: 重建表以修改字段类型
                $this->rebuildTableForSqlite();
            } else {
                // MySQL/PostgreSQL: 使用标准 ALTER
                DB::statement("ALTER TABLE products MODIFY COLUMN description LONGTEXT DEFAULT NULL");
            }
        }
    }

    /**
     * SQLite 重建表
     */
    private function rebuildTableForSqlite(): void
    {
        // 获取原表的所有列信息
        $columns = DB::select("PRAGMA table_info(products)");
        $columnNames = array_column($columns, 'name');
        $columnTypes = [];
        foreach ($columns as $col) {
            $columnTypes[$col->name] = $col->type;
        }

        // 构建 CREATE TABLE 语句中的列定义
        $columnDefs = [];
        foreach ($columns as $col) {
            $name = $col->name;
            $type = $col->type;

            // 修改 description 字段类型
            if ($name === 'description') {
                $type = 'TEXT'; // SQLite 的 TEXT 等同于 LONGTEXT
            }

            $colDef = "`{$name}` {$type}";
            if ($col->notnull) {
                $colDef .= ' NOT NULL';
            }
            if ($col->dflt_value !== null) {
                $colDef .= ' DEFAULT ' . $col->dflt_value;
            }
            $columnDefs[] = $colDef;
        }

        // 获取索引信息
        $indexes = DB::select("PRAGMA index_list(products)");
        $indexDefs = [];
        foreach ($indexes as $idx) {
            if (!$idx->unique) continue;
            $indexInfo = DB::select("PRAGMA index_info({$idx->name})");
            $idxCols = array_column($indexInfo, 'name');
            $indexDefs[] = "UNIQUE (`" . implode('`,`', $idxCols) . "`)";
        }

        // 1. 重命名原表
        DB::statement('ALTER TABLE products RENAME TO products_old');

        // 2. 创建新表
        $sql = "CREATE TABLE products (" . implode(', ', array_merge($columnDefs, $indexDefs)) . ")";
        DB::statement($sql);

        // 3. 复制数据
        $copyCols = implode(', ', array_map(fn($c) => "`{$c}`", $columnNames));
        DB::statement("INSERT INTO products ({$copyCols}) SELECT {$copyCols} FROM products_old");

        // 4. 删除原表
        DB::statement('DROP TABLE products_old');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚：SQLite 重建表比较复杂，跳过
        // 在实际生产环境中，如果需要回滚，建议手动操作或重新迁移
    }
};
