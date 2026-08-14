<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $this->setStatuses(['pending', 'active', 'rejected', 'paused', 'inactive']);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        $this->setStatuses(['pending', 'active', 'rejected', 'paused']);
    }

    /**
     * Widened with change(), not a raw `ALTER TABLE ... MODIFY`.
     *
     * MODIFY is MySQL-only syntax, so the raw statement aborted every migration run on
     * SQLite — which is what the test suite uses, so nothing could migrate and the whole
     * suite errored out. change() renders each driver's own equivalent (a native ENUM on
     * MySQL, a varchar + CHECK on SQLite).
     */
    private function setStatuses(array $statuses): void
    {
        Schema::table('users', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('pending')->change();
        });
    }
};
