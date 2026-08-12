<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `inactive` — a broker or developer deleted their own account from the app. A soft
 * delete via the existing `status` enum, not Eloquent's `SoftDeletes`: the row must
 * keep showing up in the admin's Channel Partners roster (just visibly inactive)
 * rather than disappearing from every default query the way `SoftDeletes` would make
 * it. `deleted_at` records when, for the admin to see — separate from `status` itself
 * because a future status could theoretically need its own "since when" without
 * being confused for this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'paused', 'inactive') NOT NULL DEFAULT 'pending'");

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'paused') NOT NULL DEFAULT 'pending'");
    }
};
