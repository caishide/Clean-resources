<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_extras')) {
            Schema::create('user_extras', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->decimal('bv_left', 20, 8)->default(0);
                $table->decimal('bv_right', 20, 8)->default(0);
                $table->decimal('points', 20, 8)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_extras');
    }
};
