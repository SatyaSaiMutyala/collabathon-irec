<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `role_id` is only meaningful when `users.role = 'admin'` — it's the admin-side
 * staff member's permission profile (Super Admin / Manager / custom). Brokers and
 * developers never get one; it stays null for them. Orthogonal to the existing
 * `role` enum column, which remains the actor-type discriminator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
