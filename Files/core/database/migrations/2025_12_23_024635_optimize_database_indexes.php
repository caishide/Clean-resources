<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Check if a table exists
     */
    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->shouldSkip()) {
            return;
        }

        // Products table indexes
        if ($this->tableExists('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!$this->indexExists('products', 'idx_products_status_category')) {
                    $table->index(['status', 'category_id'], 'idx_products_status_category');
                }
                if (!$this->indexExists('products', 'idx_products_category_status')) {
                    $table->index(['category_id', 'status'], 'idx_products_category_status');
                }
                if (!$this->indexExists('products', 'idx_products_status')) {
                    $table->index('status', 'idx_products_status');
                }
                if (!$this->indexExists('products', 'idx_products_category_id')) {
                    $table->index('category_id', 'idx_products_category_id');
                }
            });
        }

        // Categories table indexes
        if ($this->tableExists('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!$this->indexExists('categories', 'idx_categories_status_created')) {
                    $table->index(['status', 'created_at'], 'idx_categories_status_created');
                }
                if (!$this->indexExists('categories', 'idx_categories_status')) {
                    $table->index('status', 'idx_categories_status');
                }
            });
        }

        // General settings cache optimization
        if ($this->tableExists('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (!$this->indexExists('general_settings', 'idx_general_settings_created')) {
                    $table->index('created_at', 'idx_general_settings_created');
                }
            });
        }

        // Transactions table indexes
        if ($this->tableExists('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!$this->indexExists('transactions', 'idx_transactions_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_transactions_user_created');
                }
                if (Schema::hasColumn('transactions', 'status') && !$this->indexExists('transactions', 'idx_transactions_user_status')) {
                    $table->index(['user_id', 'status'], 'idx_transactions_user_status');
                }
                if (!$this->indexExists('transactions', 'idx_transactions_created')) {
                    $table->index('created_at', 'idx_transactions_created');
                }
                if (!$this->indexExists('transactions', 'idx_transactions_user_id')) {
                    $table->index('user_id', 'idx_transactions_user_id');
                }
            });
        }

        // Users table indexes
        if ($this->tableExists('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->indexExists('users', 'idx_users_status_created')) {
                    $table->index(['status', 'created_at'], 'idx_users_status_created');
                }
                if (!$this->indexExists('users', 'idx_users_status')) {
                    $table->index('status', 'idx_users_status');
                }
            });
        }

        // Deposits table indexes
        if ($this->tableExists('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                if (!$this->indexExists('deposits', 'idx_deposits_user_status_created')) {
                    $table->index(['user_id', 'status', 'created_at'], 'idx_deposits_user_status_created');
                }
                if (!$this->indexExists('deposits', 'idx_deposits_user_id')) {
                    $table->index('user_id', 'idx_deposits_user_id');
                }
                if (!$this->indexExists('deposits', 'idx_deposits_status')) {
                    $table->index('status', 'idx_deposits_status');
                }
                if (!$this->indexExists('deposits', 'idx_deposits_created')) {
                    $table->index('created_at', 'idx_deposits_created');
                }
            });
        }

        // Withdrawals table indexes
        if ($this->tableExists('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if (!$this->indexExists('withdrawals', 'idx_withdrawals_user_status_created')) {
                    $table->index(['user_id', 'status', 'created_at'], 'idx_withdrawals_user_status_created');
                }
                if (!$this->indexExists('withdrawals', 'idx_withdrawals_user_id')) {
                    $table->index('user_id', 'idx_withdrawals_user_id');
                }
                if (!$this->indexExists('withdrawals', 'idx_withdrawals_status')) {
                    $table->index('status', 'idx_withdrawals_status');
                }
                if (!$this->indexExists('withdrawals', 'idx_withdrawals_created')) {
                    $table->index('created_at', 'idx_withdrawals_created');
                }
            });
        }

        // Support tickets indexes
        if ($this->tableExists('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (!$this->indexExists('support_tickets', 'idx_support_tickets_user_status_created')) {
                    $table->index(['user_id', 'status', 'created_at'], 'idx_support_tickets_user_status_created');
                }
                if (!$this->indexExists('support_tickets', 'idx_support_tickets_user_id')) {
                    $table->index('user_id', 'idx_support_tickets_user_id');
                }
                if (!$this->indexExists('support_tickets', 'idx_support_tickets_status')) {
                    $table->index('status', 'idx_support_tickets_status');
                }
            });
        }

        // Support messages indexes
        if ($this->tableExists('support_messages')) {
            Schema::table('support_messages', function (Blueprint $table) {
                if (Schema::hasColumn('support_messages', 'ticket_id') && !$this->indexExists('support_messages', 'idx_support_messages_ticket_created')) {
                    $table->index(['ticket_id', 'created_at'], 'idx_support_messages_ticket_created');
                }
                if (Schema::hasColumn('support_messages', 'ticket_id') && !$this->indexExists('support_messages', 'idx_support_messages_ticket_id')) {
                    $table->index('ticket_id', 'idx_support_messages_ticket_id');
                }
            });
        }

        // Orders table indexes
        if ($this->tableExists('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!$this->indexExists('orders', 'idx_orders_user_status_created')) {
                    $table->index(['user_id', 'status', 'created_at'], 'idx_orders_user_status_created');
                }
                if (!$this->indexExists('orders', 'idx_orders_user_id')) {
                    $table->index('user_id', 'idx_orders_user_id');
                }
                if (!$this->indexExists('orders', 'idx_orders_status')) {
                    $table->index('status', 'idx_orders_status');
                }
                if (!$this->indexExists('orders', 'idx_orders_created')) {
                    $table->index('created_at', 'idx_orders_created');
                }
            });
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

        // Orders
        if ($this->tableExists('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if ($this->indexExists('orders', 'idx_orders_user_status_created')) {
                    $table->dropIndex('idx_orders_user_status_created');
                }
                if ($this->indexExists('orders', 'idx_orders_user_id')) {
                    $table->dropIndex('idx_orders_user_id');
                }
                if ($this->indexExists('orders', 'idx_orders_status')) {
                    $table->dropIndex('idx_orders_status');
                }
                if ($this->indexExists('orders', 'idx_orders_created')) {
                    $table->dropIndex('idx_orders_created');
                }
            });
        }

        // Support messages
        if ($this->tableExists('support_messages')) {
            Schema::table('support_messages', function (Blueprint $table) {
                if ($this->indexExists('support_messages', 'idx_support_messages_ticket_created')) {
                    $table->dropIndex('idx_support_messages_ticket_created');
                }
                if ($this->indexExists('support_messages', 'idx_support_messages_ticket_id')) {
                    $table->dropIndex('idx_support_messages_ticket_id');
                }
            });
        }

        // Support tickets
        if ($this->tableExists('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if ($this->indexExists('support_tickets', 'idx_support_tickets_user_status_created')) {
                    $table->dropIndex('idx_support_tickets_user_status_created');
                }
                if ($this->indexExists('support_tickets', 'idx_support_tickets_user_id')) {
                    $table->dropIndex('idx_support_tickets_user_id');
                }
                if ($this->indexExists('support_tickets', 'idx_support_tickets_status')) {
                    $table->dropIndex('idx_support_tickets_status');
                }
            });
        }

        // Withdrawals
        if ($this->tableExists('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if ($this->indexExists('withdrawals', 'idx_withdrawals_user_status_created')) {
                    $table->dropIndex('idx_withdrawals_user_status_created');
                }
                if ($this->indexExists('withdrawals', 'idx_withdrawals_user_id')) {
                    $table->dropIndex('idx_withdrawals_user_id');
                }
                if ($this->indexExists('withdrawals', 'idx_withdrawals_status')) {
                    $table->dropIndex('idx_withdrawals_status');
                }
                if ($this->indexExists('withdrawals', 'idx_withdrawals_created')) {
                    $table->dropIndex('idx_withdrawals_created');
                }
            });
        }

        // Deposits
        if ($this->tableExists('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                if ($this->indexExists('deposits', 'idx_deposits_user_status_created')) {
                    $table->dropIndex('idx_deposits_user_status_created');
                }
                if ($this->indexExists('deposits', 'idx_deposits_user_id')) {
                    $table->dropIndex('idx_deposits_user_id');
                }
                if ($this->indexExists('deposits', 'idx_deposits_status')) {
                    $table->dropIndex('idx_deposits_status');
                }
                if ($this->indexExists('deposits', 'idx_deposits_created')) {
                    $table->dropIndex('idx_deposits_created');
                }
            });
        }

        // Users
        if ($this->tableExists('users')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'idx_users_status_created')) {
                    $table->dropIndex('idx_users_status_created');
                }
                if ($this->indexExists('users', 'idx_users_status')) {
                    $table->dropIndex('idx_users_status');
                }
            });
        }

        // Transactions
        if ($this->tableExists('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if ($this->indexExists('transactions', 'idx_transactions_user_created')) {
                    $table->dropIndex('idx_transactions_user_created');
                }
                if ($this->indexExists('transactions', 'idx_transactions_user_status')) {
                    $table->dropIndex('idx_transactions_user_status');
                }
                if ($this->indexExists('transactions', 'idx_transactions_created')) {
                    $table->dropIndex('idx_transactions_created');
                }
                if ($this->indexExists('transactions', 'idx_transactions_user_id')) {
                    $table->dropIndex('idx_transactions_user_id');
                }
            });
        }

        // General settings
        if ($this->tableExists('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if ($this->indexExists('general_settings', 'idx_general_settings_created')) {
                    $table->dropIndex('idx_general_settings_created');
                }
            });
        }

        // Categories
        if ($this->tableExists('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if ($this->indexExists('categories', 'idx_categories_status_created')) {
                    $table->dropIndex('idx_categories_status_created');
                }
                if ($this->indexExists('categories', 'idx_categories_status')) {
                    $table->dropIndex('idx_categories_status');
                }
            });
        }

        // Products
        if ($this->tableExists('products')) {
            Schema::table('products', function (Blueprint $table) {
                if ($this->indexExists('products', 'idx_products_status_category')) {
                    $table->dropIndex('idx_products_status_category');
                }
                if ($this->indexExists('products', 'idx_products_category_status')) {
                    $table->dropIndex('idx_products_category_status');
                }
                if ($this->indexExists('products', 'idx_products_status')) {
                    $table->dropIndex('idx_products_status');
                }
                if ($this->indexExists('products', 'idx_products_category_id')) {
                    $table->dropIndex('idx_products_category_id');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $table = DB::getTablePrefix() . $table;
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return !empty($result);
    }
};
