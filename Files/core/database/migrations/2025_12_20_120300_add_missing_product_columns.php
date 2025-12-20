<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'specifications')) {
                $table->text('specifications')->nullable();
            }
            if (!Schema::hasColumn('products', 'thumbnail')) {
                $table->string('thumbnail', 255)->nullable();
            }
            if (!Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title', 255)->nullable();
            }
            if (!Schema::hasColumn('products', 'meta_keyword')) {
                $table->text('meta_keyword')->nullable();
            }
            if (!Schema::hasColumn('products', 'meta_description')) {
                $table->text('meta_description')->nullable();
            }
            if (!Schema::hasColumn('products', 'bv')) {
                $table->integer('bv')->default(0);
            }
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->tinyInteger('is_featured')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'specifications',
                'thumbnail',
                'meta_title',
                'meta_keyword',
                'meta_description',
                'bv',
                'is_featured',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
