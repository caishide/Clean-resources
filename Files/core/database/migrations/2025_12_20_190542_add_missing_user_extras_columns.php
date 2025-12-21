<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_extras', function (Blueprint $table) {
            $table->integer('paid_left')->default(0)->comment('付费左侧用户数')->after('points');
            $table->integer('paid_right')->default(0)->comment('付费右侧用户数')->after('paid_left');
            $table->integer('free_left')->default(0)->comment('免费左侧用户数')->after('paid_right');
            $table->integer('free_right')->default(0)->comment('免费右侧用户数')->after('free_left');
        });
    }

    public function down(): void
    {
        Schema::table('user_extras', function (Blueprint $table) {
            $table->dropColumn(['paid_left', 'paid_right', 'free_left', 'free_right']);
        });
    }
};
