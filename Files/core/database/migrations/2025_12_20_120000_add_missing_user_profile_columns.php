<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'total_invest')) {
                $table->decimal('total_invest', 28, 8)->default(0);
            }
            if (!Schema::hasColumn('users', 'dial_code')) {
                $table->string('dial_code', 40)->nullable();
            }
            if (!Schema::hasColumn('users', 'country_code')) {
                $table->string('country_code', 40)->nullable();
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 40)->nullable();
            }
            if (!Schema::hasColumn('users', 'country_name')) {
                $table->string('country_name', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'state')) {
                $table->string('state', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'zip')) {
                $table->string('zip', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable();
            }
            if (!Schema::hasColumn('users', 'image')) {
                $table->string('image', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'kv')) {
                $table->tinyInteger('kv')->default(0);
            }
            if (!Schema::hasColumn('users', 'ver_code')) {
                $table->string('ver_code', 40)->nullable();
            }
            if (!Schema::hasColumn('users', 'ver_code_send_at')) {
                $table->dateTime('ver_code_send_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'ts')) {
                $table->tinyInteger('ts')->default(0);
            }
            if (!Schema::hasColumn('users', 'tv')) {
                $table->tinyInteger('tv')->default(1);
            }
            if (!Schema::hasColumn('users', 'tsc')) {
                $table->string('tsc', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'provider')) {
                $table->text('provider')->nullable();
            }
            if (!Schema::hasColumn('users', 'kyc_data')) {
                $table->text('kyc_data')->nullable();
            }
            if (!Schema::hasColumn('users', 'kyc_rejection_reason')) {
                $table->string('kyc_rejection_reason', 255)->nullable();
            }
            if (!Schema::hasColumn('users', 'profile_complete')) {
                $table->tinyInteger('profile_complete')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'total_invest',
                'dial_code',
                'country_code',
                'mobile',
                'country_name',
                'city',
                'state',
                'zip',
                'address',
                'image',
                'kv',
                'ver_code',
                'ver_code_send_at',
                'ts',
                'tv',
                'tsc',
                'provider',
                'kyc_data',
                'kyc_rejection_reason',
                'profile_complete',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
