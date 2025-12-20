<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 40);
                $table->string('email', 40);
                $table->string('username', 40);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('image')->nullable();
                $table->string('password');
                $table->tinyInteger('status')->default(1);
                $table->tinyInteger('admin_access')->default(1);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->default(0);
                $table->string('title')->nullable();
                $table->boolean('is_read')->default(false);
                $table->text('click_url')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_password_resets')) {
            Schema::create('admin_password_resets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('email', 40);
                $table->string('token', 40);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_resets');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admins');
    }
};
