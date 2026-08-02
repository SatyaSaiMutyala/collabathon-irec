<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The developer-acceptance gate.
 *
 * Admin creates a project and assigns it to a developer; the developer must accept it
 * before any broker can see it. That makes broker visibility a TWO-key condition:
 * `listing_status = active` (admin published it) AND `developer_status = accepted`
 * (the developer owned it).
 *
 * These are deliberately separate columns rather than more values on `listing_status`:
 * they are set by different actors, and collapsing them would make "who is blocking
 * this listing" unanswerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('developer_status', ['pending', 'accepted', 'declined'])
                ->default('pending')
                ->after('listing_status');

            $table->timestamp('developer_responded_at')->nullable()->after('developer_status');
            $table->text('developer_decline_reason')->nullable()->after('developer_responded_at');

            // The developer's own inbox: "everything assigned to me, newest first",
            // and the broker-visible filter pairs this with listing_status.
            $table->index(['developer_id', 'developer_status']);
            $table->index(['listing_status', 'developer_status', 'created_at'], 'properties_visibility_index');
        });

        // Everything that already existed predates the gate and was already live to
        // brokers. Defaulting it to `pending` would silently pull every listing off the
        // platform, so existing rows are grandfathered in as accepted.
        DB::table('properties')->update([
            'developer_status' => 'accepted',
            'developer_responded_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['developer_id', 'developer_status']);
            $table->dropIndex('properties_visibility_index');
            $table->dropColumn(['developer_status', 'developer_responded_at', 'developer_decline_reason']);
        });
    }
};
