<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extensions')) {
            return;
        }

        Schema::create('extensions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('alias')->unique();
            $table->string('version')->default('1.0.0');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('author')->nullable();
            $table->longText('script')->nullable();
            $table->json('shortcode')->nullable();
            $table->string('act', 64)->index();
            $table->tinyInteger('status')->default(1)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};

