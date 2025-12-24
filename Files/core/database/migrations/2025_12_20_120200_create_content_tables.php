<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 191)->nullable();
                $table->string('slug', 191)->nullable();
                $table->string('tempname', 191)->nullable();
                $table->text('secs')->nullable();
                $table->text('seo_content')->nullable();
                $table->integer('is_default')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('frontends')) {
            Schema::create('frontends', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('data_keys', 40);
                $table->longText('data_values')->nullable();
                $table->longText('seo_content')->nullable();
                $table->string('tempname', 40)->nullable();
                $table->string('slug', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->decimal('price', 28, 8)->default(0);
                $table->integer('bv')->default(0);
                $table->decimal('ref_com', 28, 8)->default(0);
                $table->decimal('tree_com', 28, 8)->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email', 40);
                $table->string('token', 40);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('act', 40)->nullable();
                $table->text('form_data')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('pages') && DB::table('pages')->count() === 0) {
            $now = now();
            $homeSecs = json_encode([
                'about',
                'service',
                'how_it_works',
                'plan',
                'refer',
                'transaction',
                'team',
                'product',
                'testimonial',
                'blog',
            ]);
            $faqSecs = json_encode([
                'how_it_works',
                'blog',
            ]);

            DB::table('pages')->insert([
                [
                    'name' => 'Blog',
                    'slug' => 'blog',
                    'tempname' => 'templates.basic.',
                    'secs' => null,
                    'seo_content' => null,
                    'is_default' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Contact',
                    'slug' => 'contact',
                    'tempname' => 'templates.basic.',
                    'secs' => null,
                    'seo_content' => null,
                    'is_default' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Faq',
                    'slug' => 'faq',
                    'tempname' => 'templates.basic.',
                    'secs' => $faqSecs,
                    'seo_content' => null,
                    'is_default' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Home',
                    'slug' => '/',
                    'tempname' => 'templates.basic.',
                    'secs' => $homeSecs,
                    'seo_content' => null,
                    'is_default' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Product',
                    'slug' => 'product',
                    'tempname' => 'templates.basic.',
                    'secs' => null,
                    'seo_content' => null,
                    'is_default' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Products',
                    'slug' => 'products',
                    'tempname' => 'templates.basic.',
                    'secs' => null,
                    'seo_content' => null,
                    'is_default' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('frontends');
        Schema::dropIfExists('pages');
    }
};
