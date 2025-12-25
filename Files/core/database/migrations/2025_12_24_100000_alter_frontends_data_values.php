<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('frontends')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $this->sqliteRebuildTable();
            } else {
                // MySQL/PostgreSQL: 直接修改列类型
                DB::statement("ALTER TABLE frontends MODIFY COLUMN data_values LONGTEXT DEFAULT NULL");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontends')) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $this->sqliteRebuildTable(true);
            } else {
                DB::statement("ALTER TABLE frontends MODIFY COLUMN data_values TEXT DEFAULT NULL");
            }
        }
    }

    /**
     * SQLite 表重建方案
     */
    protected function sqliteRebuildTable(bool $revert = false): void
    {
        $table = 'frontends';
        $tempTable = $table . '_temp_' . time();
        $oldTable = $table . '_old_' . time();

        // 获取旧表结构信息
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $dataValuesType = $revert ? 'TEXT' : 'LONGTEXT';

        try {
            // 1. 备份原表数据
            $rows = DB::table($table)->get();

            // 2. 重命名原表
            DB::statement("ALTER TABLE {$table} RENAME TO {$oldTable}");

            // 3. 创建新表
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('data_keys')->nullable();
                $table->text('data_values')->nullable();
                $table->string('type', 40)->nullable();
                $table->string('lang', 40)->nullable();
                $table->timestamps();
            });

            // 4. 修改列为 LONGTEXT（SQLite 3.26.0+ 支持）
            try {
                DB::statement("ALTER TABLE {$table} MODIFY COLUMN data_values {$dataValuesType} DEFAULT NULL");
            } catch (\Exception $e) {
                // 如果不支持 MODIFY，则保持 TEXT 类型（SQLite 中 TEXT 也能存储大文本）
            }

            // 5. 恢复数据
            foreach ($rows as $row) {
                DB::table($table)->insert([
                    'id' => $row->id,
                    'data_keys' => $row->data_keys,
                    'data_values' => $row->data_values,
                    'type' => $row->type,
                    'lang' => $row->lang,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            // 6. 删除旧表
            DB::statement("DROP TABLE IF EXISTS {$oldTable}");

        } catch (\Exception $e) {
            // 回滚：如果失败，尝试恢复原表
            try {
                DB::statement("DROP TABLE IF EXISTS {$table}");
                DB::statement("ALTER TABLE {$oldTable} RENAME TO {$table}");
            } catch (\Exception $restoreException) {
                // 无法恢复，记录错误
                throw $e;
            }
            throw $e;
        }
    }
};
