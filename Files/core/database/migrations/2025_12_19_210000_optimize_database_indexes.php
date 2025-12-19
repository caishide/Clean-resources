<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to optimize database indexes
     */
    public function up(): void
    {
        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Composite index for common queries
                $table->index(['status', 'ev', 'sv'], 'users_status_verification_idx');

                // Index for referral queries
                $table->index(['ref_by', 'position'], 'users_referral_idx');

                // Index for position-based queries
                $table->index(['pos_id', 'position'], 'users_position_idx');

                // Index for balance queries
                $table->index('balance', 'users_balance_idx');

                // Index for login tracking
                $table->index('last_login', 'users_last_login_idx');
            });
        }

        // Transactions table indexes
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Composite index for user transactions
                $table->index(['user_id', 'created_at'], 'transactions_user_date_idx');

                // Index for transaction type queries
                $table->index(['remark', 'trx_type'], 'transactions_type_idx');

                // Index for transaction reference
                $table->index('trx', 'transactions_trx_idx')->unique();

                // Index for amount queries
                $table->index(['amount', 'trx_type'], 'transactions_amount_type_idx');
            });
        }

        // User_logins table indexes
        if (Schema::hasTable('user_logins')) {
            Schema::table('user_logins', function (Blueprint $table) {
                // Index for IP tracking
                $table->index('user_ip', 'user_logins_ip_idx');

                // Composite index for analytics
                $table->index(['user_id', 'created_at'], 'user_logins_user_date_idx');

                // Index for country queries
                $table->index('country', 'user_logins_country_idx');
            });
        }

        // Deposits table indexes
        if (Schema::hasTable('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                // Composite index for user deposits
                $table->index(['user_id', 'status'], 'deposits_user_status_idx');

                // Index for method queries
                $table->index('method_code', 'deposits_method_idx');

                // Index for transaction reference
                $table->index('trx', 'deposits_trx_idx')->unique();

                // Index for amount queries
                $table->index(['amount', 'status'], 'deposits_amount_status_idx');
            });
        }

        // Withdrawals table indexes
        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                // Composite index for user withdrawals
                $table->index(['user_id', 'status'], 'withdrawals_user_status_idx');

                // Index for method queries
                $table->index('method_id', 'withdrawals_method_idx');

                // Index for transaction reference
                $table->index('trx', 'withdrawals_trx_idx')->unique();

                // Index for amount queries
                $table->index(['amount', 'status'], 'withdrawals_amount_status_idx');
            });
        }

        // User_extras table indexes
        if (Schema::hasTable('user_extras')) {
            Schema::table('user_extras', function (Blueprint $table) {
                // Index for BV tracking
                $table->index(['bv_left', 'bv_right'], 'user_extras_bv_idx');

                // Index for user ranking
                $table->index('total_left', 'user_extras_total_left_idx');
                $table->index('total_right', 'user_extras_total_right_idx');
            });
        }

        // Bv_logs table indexes
        if (Schema::hasTable('bv_logs')) {
            Schema::table('bv_logs', function (Blueprint $table) {
                // Composite index for user BV logs
                $table->index(['user_id', 'created_at'], 'bv_logs_user_date_idx');

                // Index for transaction type
                $table->index(['trx_type', 'type'], 'bv_logs_type_idx');

                // Index for amount queries
                $table->index('amount', 'bv_logs_amount_idx');
            });
        }

        // Admin_notifications table indexes
        if (Schema::hasTable('admin_notifications')) {
            Schema::table('admin_notifications', function (Blueprint $table) {
                // Index for user notifications
                $table->index(['user_id', 'created_at'], 'admin_notifications_user_date_idx');

                // Index for read status
                $table->index('is_read', 'admin_notifications_read_idx');
            });
        }

        // General_settings table indexes
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                // Index for active settings
                $table->index('status', 'general_settings_status_idx');
            });
        }

        // Languages table indexes
        if (Schema::hasTable('languages')) {
            Schema::table('languages', function (Blueprint $table) {
                // Index for active languages
                $table->index(['is_default', 'status'], 'languages_default_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_status_verification_idx');
                $table->dropIndex('users_referral_idx');
                $table->dropIndex('users_position_idx');
                $table->dropIndex('users_balance_idx');
                $table->dropIndex('users_last_login_idx');
            });
        }

        // Transactions table
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('transactions_user_date_idx');
                $table->dropIndex('transactions_type_idx');
                $table->dropIndex('transactions_trx_idx');
                $table->dropIndex('transactions_amount_type_idx');
            });
        }

        // User_logins table
        if (Schema::hasTable('user_logins')) {
            Schema::table('user_logins', function (Blueprint $table) {
                $table->dropIndex('user_logins_ip_idx');
                $table->dropIndex('user_logins_user_date_idx');
                $table->dropIndex('user_logins_country_idx');
            });
        }

        // Deposits table
        if (Schema::hasTable('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropIndex('deposits_user_status_idx');
                $table->dropIndex('deposits_method_idx');
                $table->dropIndex('deposits_trx_idx');
                $table->dropIndex('deposits_amount_status_idx');
            });
        }

        // Withdrawals table
        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                $table->dropIndex('withdrawals_user_status_idx');
                $table->dropIndex('withdrawals_method_idx');
                $table->dropIndex('withdrawals_trx_idx');
                $table->dropIndex('withdrawals_amount_status_idx');
            });
        }

        // User_extras table
        if (Schema::hasTable('user_extras')) {
            Schema::table('user_extras', function (Blueprint $table) {
                $table->dropIndex('user_extras_bv_idx');
                $table->dropIndex('user_extras_total_left_idx');
                $table->dropIndex('user_extras_total_right_idx');
            });
        }

        // Bv_logs table
        if (Schema::hasTable('bv_logs')) {
            Schema::table('bv_logs', function (Blueprint $table) {
                $table->dropIndex('bv_logs_user_date_idx');
                $table->dropIndex('bv_logs_type_idx');
                $table->dropIndex('bv_logs_amount_idx');
            });
        }

        // Admin_notifications table
        if (Schema::hasTable('admin_notifications')) {
            Schema::table('admin_notifications', function (Blueprint $table) {
                $table->dropIndex('admin_notifications_user_date_idx');
                $table->dropIndex('admin_notifications_read_idx');
            });
        }

        // General_settings table
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropIndex('general_settings_status_idx');
            });
        }

        // Languages table
        if (Schema::hasTable('languages')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->dropIndex('languages_default_status_idx');
            });
        }
    }
};
