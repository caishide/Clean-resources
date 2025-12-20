<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_logins')) {
            Schema::create('user_logins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('user_ip', 40)->nullable();
                $table->string('city', 40)->nullable();
                $table->string('country', 40)->nullable();
                $table->string('country_code', 40)->nullable();
                $table->string('longitude', 40)->nullable();
                $table->string('latitude', 40)->nullable();
                $table->string('browser', 40)->nullable();
                $table->string('os', 40)->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('user_logins', function (Blueprint $table) {
            if (!Schema::hasColumn('user_logins', 'country_code')) {
                $table->string('country_code', 40)->nullable();
            }
            if (!Schema::hasColumn('user_logins', 'longitude')) {
                $table->string('longitude', 40)->nullable();
            }
            if (!Schema::hasColumn('user_logins', 'latitude')) {
                $table->string('latitude', 40)->nullable();
            }
            if (!Schema::hasColumn('user_logins', 'browser')) {
                $table->string('browser', 40)->nullable();
            }
            if (!Schema::hasColumn('user_logins', 'os')) {
                $table->string('os', 40)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('user_logins')) {
            return;
        }

        Schema::table('user_logins', function (Blueprint $table) {
            if (Schema::hasColumn('user_logins', 'os')) {
                $table->dropColumn('os');
            }
            if (Schema::hasColumn('user_logins', 'browser')) {
                $table->dropColumn('browser');
            }
            if (Schema::hasColumn('user_logins', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('user_logins', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('user_logins', 'country_code')) {
                $table->dropColumn('country_code');
            }
        });
    }
};
