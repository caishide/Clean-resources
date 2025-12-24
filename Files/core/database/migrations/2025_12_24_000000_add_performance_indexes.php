<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Skip migration for SQLite (used in tests)
     */
    private function shouldSkip(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Run the migrations.
     *
     * 添加性能优化所需的所有索引
     * 包含：pending_bonuses, admins, admin_notifications, admin_password_resets,
     *       pv_ledger, pages, plans, orders 等表的索引
     */
    public function up(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        // pending_bonuses 表
        if (Schema::hasTable('pending_bonuses') && !$this->indexExists('pending_bonuses', 'idx_pb_status')) {
            DB::statement('ALTER TABLE pending_bonuses ADD INDEX idx_pb_status (status)');
        }

        // admins 表
        if (Schema::hasTable('admins')) {
            if (!$this->indexExists('admins', 'idx_admins_email')) {
                DB::statement('ALTER TABLE admins ADD UNIQUE INDEX idx_admins_email (email)');
            }
            if (!$this->indexExists('admins', 'idx_admins_username')) {
                DB::statement('ALTER TABLE admins ADD INDEX idx_admins_username (username)');
            }
            if (!$this->indexExists('admins', 'idx_admins_status')) {
                DB::statement('ALTER TABLE admins ADD INDEX idx_admins_status (status)');
            }
        }

        // admin_notifications 表
        if (Schema::hasTable('admin_notifications') && !$this->indexExists('admin_notifications', 'idx_an_user_read')) {
            DB::statement('ALTER TABLE admin_notifications ADD INDEX idx_an_user_read (user_id, is_read)');
        }

        // admin_password_resets 表
        if (Schema::hasTable('admin_password_resets') && !$this->indexExists('admin_password_resets', 'idx_apr_status')) {
            DB::statement('ALTER TABLE admin_password_resets ADD INDEX idx_apr_status (status)');
        }

        // pv_ledger 表
        if (Schema::hasTable('pv_ledger')) {
            if (!$this->indexExists('pv_ledger', 'idx_pl_position')) {
                DB::statement('ALTER TABLE pv_ledger ADD INDEX idx_pl_position (position)');
            }
            if (!$this->indexExists('pv_ledger', 'idx_pl_level')) {
                DB::statement('ALTER TABLE pv_ledger ADD INDEX idx_pl_level (level)');
            }
            if (!$this->indexExists('pv_ledger', 'idx_pl_source_type')) {
                DB::statement('ALTER TABLE pv_ledger ADD INDEX idx_pl_source_type (source_type)');
            }
            if (!$this->indexExists('pv_ledger', 'idx_pl_user_position')) {
                DB::statement('ALTER TABLE pv_ledger ADD INDEX idx_pl_user_position (user_id, position)');
            }
        }

        // pages 表
        if (Schema::hasTable('pages')) {
            if (!$this->indexExists('pages', 'idx_pages_slug')) {
                DB::statement('ALTER TABLE pages ADD UNIQUE INDEX idx_pages_slug (slug)');
            }
            if (!$this->indexExists('pages', 'idx_pages_is_default')) {
                DB::statement('ALTER TABLE pages ADD INDEX idx_pages_is_default (is_default)');
            }
            if (!$this->indexExists('pages', 'idx_pages_tempname_slug')) {
                DB::statement('ALTER TABLE pages ADD INDEX idx_pages_tempname_slug (tempname, slug)');
            }
        }

        // plans 表
        if (Schema::hasTable('plans') && !$this->indexExists('plans', 'idx_plans_status')) {
            DB::statement('ALTER TABLE plans ADD INDEX idx_plans_status (status)');
        }

        // orders 表
        if (Schema::hasTable('orders')) {
            if (!$this->indexExists('orders', 'idx_orders_trx')) {
                DB::statement('ALTER TABLE orders ADD UNIQUE INDEX idx_orders_trx (trx)');
            }
            if (!$this->indexExists('orders', 'idx_orders_product_id')) {
                DB::statement('ALTER TABLE orders ADD INDEX idx_orders_product_id (product_id)');
            }
            if (!$this->indexExists('orders', 'idx_orders_user_status')) {
                DB::statement('ALTER TABLE orders ADD INDEX idx_orders_user_status (user_id, status)');
            }
        }

        // user_extras 表 - 添加user_id索引
        if (Schema::hasTable('user_extras') && !$this->indexExists('user_extras', 'idx_ue_user_id')) {
            DB::statement('ALTER TABLE user_extras ADD INDEX idx_ue_user_id (user_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        // 回滚时删除索引（按创建顺序的逆序）

        // user_extras 表
        if (Schema::hasTable('user_extras') && $this->indexExists('user_extras', 'idx_ue_user_id')) {
            DB::statement('ALTER TABLE user_extras DROP INDEX idx_ue_user_id');
        }

        // orders 表
        if (Schema::hasTable('orders')) {
            if ($this->indexExists('orders', 'idx_orders_user_status')) {
                DB::statement('ALTER TABLE orders DROP INDEX idx_orders_user_status');
            }
            if ($this->indexExists('orders', 'idx_orders_product_id')) {
                DB::statement('ALTER TABLE orders DROP INDEX idx_orders_product_id');
            }
            if ($this->indexExists('orders', 'idx_orders_trx')) {
                DB::statement('ALTER TABLE orders DROP INDEX idx_orders_trx');
            }
        }

        // plans 表
        if (Schema::hasTable('plans') && $this->indexExists('plans', 'idx_plans_status')) {
            DB::statement('ALTER TABLE plans DROP INDEX idx_plans_status');
        }

        // pages 表
        if (Schema::hasTable('pages')) {
            if ($this->indexExists('pages', 'idx_pages_tempname_slug')) {
                DB::statement('ALTER TABLE pages DROP INDEX idx_pages_tempname_slug');
            }
            if ($this->indexExists('pages', 'idx_pages_is_default')) {
                DB::statement('ALTER TABLE pages DROP INDEX idx_pages_is_default');
            }
            if ($this->indexExists('pages', 'idx_pages_slug')) {
                DB::statement('ALTER TABLE pages DROP INDEX idx_pages_slug');
            }
        }

        // pv_ledger 表
        if (Schema::hasTable('pv_ledger')) {
            if ($this->indexExists('pv_ledger', 'idx_pl_user_position')) {
                DB::statement('ALTER TABLE pv_ledger DROP INDEX idx_pl_user_position');
            }
            if ($this->indexExists('pv_ledger', 'idx_pl_source_type')) {
                DB::statement('ALTER TABLE pv_ledger DROP INDEX idx_pl_source_type');
            }
            if ($this->indexExists('pv_ledger', 'idx_pl_level')) {
                DB::statement('ALTER TABLE pv_ledger DROP INDEX idx_pl_level');
            }
            if ($this->indexExists('pv_ledger', 'idx_pl_position')) {
                DB::statement('ALTER TABLE pv_ledger DROP INDEX idx_pl_position');
            }
        }

        // admin_password_resets 表
        if (Schema::hasTable('admin_password_resets') && $this->indexExists('admin_password_resets', 'idx_apr_status')) {
            DB::statement('ALTER TABLE admin_password_resets DROP INDEX idx_apr_status');
        }

        // admin_notifications 表
        if (Schema::hasTable('admin_notifications') && $this->indexExists('admin_notifications', 'idx_an_user_read')) {
            DB::statement('ALTER TABLE admin_notifications DROP INDEX idx_an_user_read');
        }

        // admins 表
        if (Schema::hasTable('admins')) {
            if ($this->indexExists('admins', 'idx_admins_status')) {
                DB::statement('ALTER TABLE admins DROP INDEX idx_admins_status');
            }
            if ($this->indexExists('admins', 'idx_admins_username')) {
                DB::statement('ALTER TABLE admins DROP INDEX idx_admins_username');
            }
            if ($this->indexExists('admins', 'idx_admins_email')) {
                DB::statement('ALTER TABLE admins DROP INDEX idx_admins_email');
            }
        }

        // pending_bonuses 表
        if (Schema::hasTable('pending_bonuses') && $this->indexExists('pending_bonuses', 'idx_pb_status')) {
            DB::statement('ALTER TABLE pending_bonuses DROP INDEX idx_pb_status');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $table = DB::getTablePrefix() . $table;
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return !empty($result);
    }
};
