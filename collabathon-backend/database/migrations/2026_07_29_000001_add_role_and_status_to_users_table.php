<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One `users` table for all three actors. `email` is the login key for every role
 * (admin session, broker + developer Sanctum tokens); `mobile` is profile data only.
 *
 * `status` is the approval gate: a broker registers as `pending` and cannot obtain a
 * token until an admin flips it to `active`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'broker', 'developer'])->default('broker')->after('email');
            $table->enum('status', ['pending', 'active', 'rejected', 'paused'])->default('pending')->after('role');
            $table->string('mobile', 32)->nullable()->after('status');
            $table->string('avatar_path')->nullable()->after('mobile');
            $table->timestamp('last_login_at')->nullable();

            // Every admin list is filtered by role+status and ordered by recency.
            $table->index(['role', 'status']);
            $table->index(['role', 'created_at']);
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropIndex(['role', 'created_at']);
            $table->dropIndex(['mobile']);
            $table->dropColumn(['role', 'status', 'mobile', 'avatar_path', 'last_login_at']);
        });
    }
};
