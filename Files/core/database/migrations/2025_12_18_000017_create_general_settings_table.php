<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('general_settings')) {
            Schema::create('general_settings', function (Blueprint $table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('available_version')->nullable();
                $table->json('bonus_config')->nullable();
                $table->boolean('force_ssl')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('general_settings');
    }
};
