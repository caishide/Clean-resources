<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_configs', function (Blueprint $table) {
            $table->id();
            $table->string('version_code', 50)->unique()->index();
            $table->json('config_json');
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_configs');
    }
};
