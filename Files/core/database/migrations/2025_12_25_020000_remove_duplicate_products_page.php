<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 删除重复的 "Products" 页面 (slug: products)
        // 保留 "Product" 页面 (slug: product)
        DB::table('pages')
            ->where('slug', 'products')
            ->delete();
            
        echo "已删除重复的 Products 页面 (slug: products)\n";
        echo "保留 Product 页面 (slug: product)\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚: 重新插入 Products 页面
        DB::table('pages')->insert([
            'name' => 'Products',
            'slug' => 'products',
            'tempname' => 'templates.basic.',
            'secs' => null,
            'seo_content' => null,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};