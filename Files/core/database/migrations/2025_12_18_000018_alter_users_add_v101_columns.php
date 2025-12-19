<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'username')) {
                    $table->string('username')->unique()->nullable();
                }
                if (!Schema::hasColumn('users', 'firstname')) {
                    $table->string('firstname')->nullable();
                }
                if (!Schema::hasColumn('users', 'lastname')) {
                    $table->string('lastname')->nullable();
                }
                $numericCols = [
                    ['plan_id', 'tinyInteger', 0],
                    ['pos_id', 'unsignedBigInteger', 0],
                    ['position', 'tinyInteger', 0],
                    ['ref_by', 'unsignedBigInteger', 0],
                    ['status', 'tinyInteger', 1],
                    ['ev', 'tinyInteger', 1],
                    ['sv', 'tinyInteger', 1],
                ];
                foreach ($numericCols as [$col, $type, $default]) {
                    if (!Schema::hasColumn('users', $col)) {
                        $table->{$type}($col)->default($default);
                    }
                }
                if (!Schema::hasColumn('users', 'balance')) {
                    $table->decimal('balance', 20, 8)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        // no rollback to keep test schema simple
    }
};
