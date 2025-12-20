<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to create missing tables
     */
    public function up(): void
    {
        // Categories table
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        // Products table
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('price', 20, 8);
                $table->decimal('cost', 20, 8)->nullable();
                $table->integer('quantity')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->unsignedBigInteger('category_id')->nullable();
                $table->timestamps();
                
                $table->index('category_id');
            });
        }

        // Deposits table
        if (!Schema::hasTable('deposits')) {
            Schema::create('deposits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('method_code', 40);
                $table->string('method_currency', 40);
                $table->decimal('amount', 20, 8);
                $table->decimal('charge', 20, 8);
                $table->decimal('final_amount', 20, 8);
                $table->string('trx', 40)->unique();
                $table->tinyInteger('status')->default(0);
                $table->json('detail')->nullable();
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('status');
            });
        }

        // Withdrawals table
        if (!Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('method_code', 40);
                $table->string('method_currency', 40);
                $table->decimal('amount', 20, 8);
                $table->decimal('charge', 20, 8);
                $table->decimal('final_amount', 20, 8);
                $table->string('trx', 40)->unique();
                $table->tinyInteger('status')->default(0);
                $table->json('detail')->nullable();
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('status');
            });
        }

        // Orders table
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('product_id');
                $table->integer('quantity')->default(1);
                $table->decimal('price', 20, 8);
                $table->decimal('total_price', 20, 8);
                $table->decimal('amount', 20, 8);
                $table->decimal('commission', 20, 8)->default(0);
                $table->string('trx', 40);
                $table->tinyInteger('status')->default(0);
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('product_id');
            });
        }

        // Bv logs table
        if (!Schema::hasTable('bv_logs')) {
            Schema::create('bv_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->decimal('amount', 20, 8);
                $table->string('trx_type', 20);
                $table->string('type', 20);
                $table->string('trx', 40)->nullable();
                $table->text('remark')->nullable();
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bv_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
