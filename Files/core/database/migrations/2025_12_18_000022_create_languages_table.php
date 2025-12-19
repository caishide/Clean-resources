<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->tinyInteger('is_default')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
