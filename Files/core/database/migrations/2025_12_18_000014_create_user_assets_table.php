<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_assets')) {
            Schema::create('user_assets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique()->index();
                $table->decimal('points', 20, 8)->default(0)->comment('莲子积分余额');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assets');
    }
};
