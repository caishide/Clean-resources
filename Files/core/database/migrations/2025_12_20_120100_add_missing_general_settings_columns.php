<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('general_settings')) {
            return;
        }

        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'cur_text')) {
                $table->string('cur_text', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'cur_sym')) {
                $table->string('cur_sym', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'email_from')) {
                $table->string('email_from', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'email_from_name')) {
                $table->string('email_from_name', 255)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'email_template')) {
                $table->text('email_template')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'sms_template')) {
                $table->string('sms_template', 255)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'sms_from')) {
                $table->string('sms_from', 255)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'push_title')) {
                $table->string('push_title', 255)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'push_template')) {
                $table->string('push_template', 255)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'sms_api')) {
                $table->string('sms_api', 255)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'base_color')) {
                $table->string('base_color', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'secondary_color')) {
                $table->string('secondary_color', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'mail_config')) {
                $table->text('mail_config')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'sms_config')) {
                $table->text('sms_config')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'firebase_config')) {
                $table->text('firebase_config')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'kv')) {
                $table->tinyInteger('kv')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'ev')) {
                $table->tinyInteger('ev')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'en')) {
                $table->tinyInteger('en')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'sv')) {
                $table->tinyInteger('sv')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'sn')) {
                $table->tinyInteger('sn')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'pn')) {
                $table->tinyInteger('pn')->default(1);
            }
            if (!Schema::hasColumn('general_settings', 'secure_password')) {
                $table->tinyInteger('secure_password')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'agree')) {
                $table->tinyInteger('agree')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'registration')) {
                $table->tinyInteger('registration')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'active_template')) {
                $table->string('active_template', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'sys_version')) {
                $table->text('sys_version')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'bv_price')) {
                $table->decimal('bv_price', 28, 8)->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'total_bv')) {
                $table->decimal('total_bv', 28, 8)->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'max_bv')) {
                $table->integer('max_bv')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'cary_flash')) {
                $table->tinyInteger('cary_flash')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'notice')) {
                $table->text('notice')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'free_user_notice')) {
                $table->text('free_user_notice')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'matching_bonus_time')) {
                $table->string('matching_bonus_time', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'matching_when')) {
                $table->string('matching_when', 40)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'last_paid')) {
                $table->dateTime('last_paid')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'last_cron')) {
                $table->timestamp('last_cron')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'bal_trans_per_charge')) {
                $table->decimal('bal_trans_per_charge', 5, 2)->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'bal_trans_fixed_charge')) {
                $table->decimal('bal_trans_fixed_charge', 28, 8)->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'system_customized')) {
                $table->tinyInteger('system_customized')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'paginate_number')) {
                $table->integer('paginate_number')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'currency_format')) {
                $table->tinyInteger('currency_format')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'multi_language')) {
                $table->tinyInteger('multi_language')->default(1);
            }
            if (!Schema::hasColumn('general_settings', 'global_shortcodes')) {
                $table->text('global_shortcodes')->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'maintenance_mode')) {
                $table->tinyInteger('maintenance_mode')->default(0);
            }
            if (!Schema::hasColumn('general_settings', 'socialite_credentials')) {
                $table->longText('socialite_credentials')->nullable();
            }
        });

        if (DB::table('general_settings')->count() === 0) {
            DB::table('general_settings')->insert([
                'site_name' => 'BinaryEcom',
                'cur_text' => 'USD',
                'cur_sym' => '$',
                'email_from' => 'info@example.com',
                'email_from_name' => '{{site_name}}',
                'active_template' => 'basic',
                'paginate_number' => 20,
                'multi_language' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('general_settings')) {
            return;
        }

        Schema::table('general_settings', function (Blueprint $table) {
            $columns = [
                'cur_text',
                'cur_sym',
                'email_from',
                'email_from_name',
                'email_template',
                'sms_template',
                'sms_from',
                'push_title',
                'push_template',
                'sms_api',
                'base_color',
                'secondary_color',
                'mail_config',
                'sms_config',
                'firebase_config',
                'kv',
                'ev',
                'en',
                'sv',
                'sn',
                'pn',
                'secure_password',
                'agree',
                'registration',
                'active_template',
                'sys_version',
                'bv_price',
                'total_bv',
                'max_bv',
                'cary_flash',
                'notice',
                'free_user_notice',
                'matching_bonus_time',
                'matching_when',
                'last_paid',
                'last_cron',
                'bal_trans_per_charge',
                'bal_trans_fixed_charge',
                'system_customized',
                'paginate_number',
                'currency_format',
                'multi_language',
                'global_shortcodes',
                'maintenance_mode',
                'socialite_credentials',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('general_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
