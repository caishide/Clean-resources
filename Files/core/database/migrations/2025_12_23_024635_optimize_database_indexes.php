<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Frontend table indexes (for blog, FAQ, etc.)
        Schema::table('frontends', function (Blueprint $table) {
            if (!$this->indexExists('frontends', 'idx_frontend_data_keys_created')) {
                $table->index(['data_keys', 'created_at'], 'idx_frontend_data_keys_created');
            }
            if (!$this->indexExists('frontends', 'idx_frontend_slug_data_keys')) {
                $table->index(['slug', 'data_keys'], 'idx_frontend_slug_data_keys');
            }
            if (!$this->indexExists('frontends', 'idx_frontend_tempname')) {
                $table->index('tempname', 'idx_frontend_tempname');
            }
        });

        // Pages table indexes
        Schema::table('pages', function (Blueprint $table) {
            if (!$this->indexExists('pages', 'idx_pages_tempname_slug')) {
                $table->index(['tempname', 'slug'], 'idx_pages_tempname_slug');
            }
            if (!$this->indexExists('pages', 'idx_pages_tempname')) {
                $table->index('tempname', 'idx_pages_tempname');
            }
        });

        // Products table indexes
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

        // Categories table indexes
        Schema::table('categories', function (Blueprint $table) {
            if (!$this->indexExists('categories', 'idx_categories_status_created')) {
                $table->index(['status', 'created_at'], 'idx_categories_status_created');
            }
            if (!$this->indexExists('categories', 'idx_categories_status')) {
                $table->index('status', 'idx_categories_status');
            }
        });

        // General settings cache optimization
        Schema::table('general_settings', function (Blueprint $table) {
            if (!$this->indexExists('general_settings', 'idx_general_settings_created')) {
                $table->index('created_at', 'idx_general_settings_created');
            }
        });

        // Transactions table indexes
        Schema::table('transactions', function (Blueprint $table) {
            if (!$this->indexExists('transactions', 'idx_transactions_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_transactions_user_created');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_user_status')) {
                $table->index(['user_id', 'status'], 'idx_transactions_user_status');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_created')) {
                $table->index('created_at', 'idx_transactions_created');
            }
            if (!$this->indexExists('transactions', 'idx_transactions_user_id')) {
                $table->index('user_id', 'idx_transactions_user_id');
            }
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'idx_users_status_created')) {
                $table->index(['status', 'created_at'], 'idx_users_status_created');
            }
            if (!$this->indexExists('users', 'idx_users_status')) {
                $table->index('status', 'idx_users_status');
            }
        });

        // Deposits table indexes
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

        // Withdrawals table indexes
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

        // Support tickets indexes
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

        // Support messages indexes
        Schema::table('support_messages', function (Blueprint $table) {
            if (!$this->indexExists('support_messages', 'idx_support_messages_ticket_created')) {
                $table->index(['ticket_id', 'created_at'], 'idx_support_messages_ticket_created');
            }
            if (!$this->indexExists('support_messages', 'idx_support_messages_ticket_id')) {
                $table->index('ticket_id', 'idx_support_messages_ticket_id');
            }
        });

        // Orders table indexes
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes in reverse order

        // Orders
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

        // Support messages
        Schema::table('support_messages', function (Blueprint $table) {
            if ($this->indexExists('support_messages', 'idx_support_messages_ticket_created')) {
                $table->dropIndex('idx_support_messages_ticket_created');
            }
            if ($this->indexExists('support_messages', 'idx_support_messages_ticket_id')) {
                $table->dropIndex('idx_support_messages_ticket_id');
            }
        });

        // Support tickets
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

        // Withdrawals
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

        // Deposits
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

        // Users
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_status_created')) {
                $table->dropIndex('idx_users_status_created');
            }
            if ($this->indexExists('users', 'idx_users_status')) {
                $table->dropIndex('idx_users_status');
            }
        });

        // Transactions
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

        // General settings
        Schema::table('general_settings', function (Blueprint $table) {
            if ($this->indexExists('general_settings', 'idx_general_settings_created')) {
                $table->dropIndex('idx_general_settings_created');
            }
        });

        // Categories
        Schema::table('categories', function (Blueprint $table) {
            if ($this->indexExists('categories', 'idx_categories_status_created')) {
                $table->dropIndex('idx_categories_status_created');
            }
            if ($this->indexExists('categories', 'idx_categories_status')) {
                $table->dropIndex('idx_categories_status');
            }
        });

        // Products
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

        // Pages
        Schema::table('pages', function (Blueprint $table) {
            if ($this->indexExists('pages', 'idx_pages_tempname_slug')) {
                $table->dropIndex('idx_pages_tempname_slug');
            }
            if ($this->indexExists('pages', 'idx_pages_tempname')) {
                $table->dropIndex('idx_pages_tempname');
            }
        });

        // Frontend
        Schema::table('frontends', function (Blueprint $table) {
            if ($this->indexExists('frontends', 'idx_frontend_data_keys_created')) {
                $table->dropIndex('idx_frontend_data_keys_created');
            }
            if ($this->indexExists('frontends', 'idx_frontend_slug_data_keys')) {
                $table->dropIndex('idx_frontend_slug_data_keys');
            }
            if ($this->indexExists('frontends', 'idx_frontend_tempname')) {
                $table->dropIndex('idx_frontend_tempname');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $table = DB::getTablePrefix() . $table;
        $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return !empty($result);
    }
};
