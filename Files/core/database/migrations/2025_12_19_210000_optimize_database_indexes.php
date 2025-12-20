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
                if (Schema::hasColumn('users', 'status') && Schema::hasColumn('users', 'ev') && Schema::hasColumn('users', 'sv')) {
                    $table->index(['status', 'ev', 'sv'], 'users_status_verification_idx');
                }

                // Index for referral queries
                if (Schema::hasColumn('users', 'ref_by') && Schema::hasColumn('users', 'position')) {
                    $table->index(['ref_by', 'position'], 'users_referral_idx');
                }

                // Index for position-based queries
                if (Schema::hasColumn('users', 'pos_id') && Schema::hasColumn('users', 'position')) {
                    $table->index(['pos_id', 'position'], 'users_position_idx');
                }

                // Index for balance queries
                if (Schema::hasColumn('users', 'balance')) {
                    $table->index('balance', 'users_balance_idx');
                }
            });
        }

        // Transactions table indexes
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Composite index for user transactions
                if (Schema::hasColumn('transactions', 'user_id') && Schema::hasColumn('transactions', 'created_at')) {
                    $table->index(['user_id', 'created_at'], 'transactions_user_date_idx');
                }

                // Index for transaction type queries
                if (Schema::hasColumn('transactions', 'remark') && Schema::hasColumn('transactions', 'trx_type')) {
                    $table->index(['remark', 'trx_type'], 'transactions_type_idx');
                }

                // Index for transaction reference
                if (Schema::hasColumn('transactions', 'trx')) {
                    $table->index('trx', 'transactions_trx_idx')->unique();
                }

                // Index for amount queries
                if (Schema::hasColumn('transactions', 'amount') && Schema::hasColumn('transactions', 'trx_type')) {
                    $table->index(['amount', 'trx_type'], 'transactions_amount_type_idx');
                }
            });
        }

        // User_logins table indexes
        if (Schema::hasTable('user_logins')) {
            Schema::table('user_logins', function (Blueprint $table) {
                // Index for IP tracking
                if (Schema::hasColumn('user_logins', 'user_ip')) {
                    $table->index('user_ip', 'user_logins_ip_idx');
                }

                // Composite index for analytics
                if (Schema::hasColumn('user_logins', 'user_id') && Schema::hasColumn('user_logins', 'created_at')) {
                    $table->index(['user_id', 'created_at'], 'user_logins_user_date_idx');
                }

                // Index for country queries
                if (Schema::hasColumn('user_logins', 'country')) {
                    $table->index('country', 'user_logins_country_idx');
                }
            });
        }

        // Deposits table indexes
        if (Schema::hasTable('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                // Composite index for user deposits
                if (Schema::hasColumn('deposits', 'user_id') && Schema::hasColumn('deposits', 'status')) {
                    $table->index(['user_id', 'status'], 'deposits_user_status_idx');
                }

                // Index for method queries
                if (Schema::hasColumn('deposits', 'method_code')) {
                    $table->index('method_code', 'deposits_method_idx');
                }

                // Index for transaction reference
                if (Schema::hasColumn('deposits', 'trx')) {
                    $table->index('trx', 'deposits_trx_idx')->unique();
                }

                // Index for amount queries
                if (Schema::hasColumn('deposits', 'amount') && Schema::hasColumn('deposits', 'status')) {
                    $table->index(['amount', 'status'], 'deposits_amount_status_idx');
                }
            });
        }

        // Withdrawals table indexes
        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                // Composite index for user withdrawals
                if (Schema::hasColumn('withdrawals', 'user_id') && Schema::hasColumn('withdrawals', 'status')) {
                    $table->index(['user_id', 'status'], 'withdrawals_user_status_idx');
                }

                // Index for method queries
                if (Schema::hasColumn('withdrawals', 'method_id')) {
                    $table->index('method_id', 'withdrawals_method_idx');
                }

                // Index for transaction reference
                if (Schema::hasColumn('withdrawals', 'trx')) {
                    $table->index('trx', 'withdrawals_trx_idx')->unique();
                }

                // Index for amount queries
                if (Schema::hasColumn('withdrawals', 'amount') && Schema::hasColumn('withdrawals', 'status')) {
                    $table->index(['amount', 'status'], 'withdrawals_amount_status_idx');
                }
            });
        }

        // User_extras table indexes
        if (Schema::hasTable('user_extras')) {
            Schema::table('user_extras', function (Blueprint $table) {
                // Index for BV tracking
                if (Schema::hasColumn('user_extras', 'bv_left') && Schema::hasColumn('user_extras', 'bv_right')) {
                    $table->index(['bv_left', 'bv_right'], 'user_extras_bv_idx');
                }

                // Index for user ranking (only if columns exist)
                if (Schema::hasColumn('user_extras', 'total_left')) {
                    $table->index('total_left', 'user_extras_total_left_idx');
                }
                if (Schema::hasColumn('user_extras', 'total_right')) {
                    $table->index('total_right', 'user_extras_total_right_idx');
                }
            });
        }

        // Bv_logs table indexes
        if (Schema::hasTable('bv_logs')) {
            Schema::table('bv_logs', function (Blueprint $table) {
                // Composite index for user BV logs
                if (Schema::hasColumn('bv_logs', 'user_id') && Schema::hasColumn('bv_logs', 'created_at')) {
                    $table->index(['user_id', 'created_at'], 'bv_logs_user_date_idx');
                }

                // Index for transaction type
                if (Schema::hasColumn('bv_logs', 'trx_type') && Schema::hasColumn('bv_logs', 'type')) {
                    $table->index(['trx_type', 'type'], 'bv_logs_type_idx');
                }

                // Index for amount queries
                if (Schema::hasColumn('bv_logs', 'amount')) {
                    $table->index('amount', 'bv_logs_amount_idx');
                }
            });
        }

        // Admin_notifications table indexes
        if (Schema::hasTable('admin_notifications')) {
            Schema::table('admin_notifications', function (Blueprint $table) {
                // Index for user notifications
                if (Schema::hasColumn('admin_notifications', 'user_id') && Schema::hasColumn('admin_notifications', 'created_at')) {
                    $table->index(['user_id', 'created_at'], 'admin_notifications_user_date_idx');
                }

                // Index for read status
                if (Schema::hasColumn('admin_notifications', 'is_read')) {
                    $table->index('is_read', 'admin_notifications_read_idx');
                }
            });
        }

        // General_settings table indexes
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                // Index for active settings
                if (Schema::hasColumn('general_settings', 'status')) {
                    $table->index('status', 'general_settings_status_idx');
                }
            });
        }

        // Languages table indexes
        if (Schema::hasTable('languages')) {
            Schema::table('languages', function (Blueprint $table) {
                // Index for active languages
                if (Schema::hasColumn('languages', 'is_default') && Schema::hasColumn('languages', 'status')) {
                    $table->index(['is_default', 'status'], 'languages_default_status_idx');
                }
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
                if (Schema::hasColumn('user_extras', 'bv_left') && Schema::hasColumn('user_extras', 'bv_right')) {
                    $table->dropIndex('user_extras_bv_idx');
                }
                if (Schema::hasColumn('user_extras', 'total_left')) {
                    $table->dropIndex('user_extras_total_left_idx');
                }
                if (Schema::hasColumn('user_extras', 'total_right')) {
                    $table->dropIndex('user_extras_total_right_idx');
                }
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
