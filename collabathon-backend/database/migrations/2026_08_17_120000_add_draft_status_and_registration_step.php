<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `draft` — a channel partner mid-registration: `register/start` (step 1) has created
 * the row, but they haven't reached step 3's final submit yet. Distinct from `pending`
 * (submitted, awaiting admin review) so a half-finished registration never shows up in
 * the admin's approval queue — see ApprovalController's `status(User::STATUS_PENDING)`
 * scope, which already excludes anything else by construction.
 *
 * `registration_step` tracks the furthest step a broker has reached (1/2/3), so the app
 * can resume a `draft` session on the exact step it left off on instead of restarting
 * the wizard from step 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'active', 'rejected', 'paused', 'inactive'])
                ->default('pending')
                ->change();
        });

        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('registration_step')->default(1)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn('registration_step');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'rejected', 'paused', 'inactive'])
                ->default('pending')
                ->change();
        });
    }
};
