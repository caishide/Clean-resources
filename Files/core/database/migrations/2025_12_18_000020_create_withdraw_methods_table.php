<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('withdraw_methods')) {
            Schema::create('withdraw_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('currency', 10)->default('USD');
                $table->decimal('min_limit', 20, 8)->default(0);
                $table->decimal('max_limit', 20, 8)->default(0);
                $table->decimal('fixed_charge', 20, 8)->default(0);
                $table->decimal('percent_charge', 10, 4)->default(0);
                $table->decimal('rate', 20, 8)->default(1);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_methods');
    }
};
