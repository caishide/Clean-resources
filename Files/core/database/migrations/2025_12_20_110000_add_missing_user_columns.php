<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add missing user columns
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'ban_reason')) {
                    $table->string('ban_reason')->nullable();
                }
                if (!Schema::hasColumn('users', 'kv')) {
                    $table->tinyInteger('kv')->default(1);
                }
                if (!Schema::hasColumn('users', 'ts')) {
                    $table->tinyInteger('ts')->default(0);
                }
                if (!Schema::hasColumn('users', 'tv')) {
                    $table->tinyInteger('tv')->default(1);
                }
                if (!Schema::hasColumn('users', 'address')) {
                    $table->string('address')->nullable();
                }
                if (!Schema::hasColumn('users', 'city')) {
                    $table->string('city')->nullable();
                }
                if (!Schema::hasColumn('users', 'state')) {
                    $table->string('state')->nullable();
                }
                if (!Schema::hasColumn('users', 'zip')) {
                    $table->string('zip')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'ban_reason')) {
                    $table->dropColumn('ban_reason');
                }
            });
        }
    }
};
