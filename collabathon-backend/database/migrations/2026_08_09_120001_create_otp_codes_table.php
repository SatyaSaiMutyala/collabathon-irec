<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per OTP challenge — mobile-number sign-in for channel partners. `code` is
 * hashed the same way a password is, never stored plain, since a leaked row should not
 * hand out a live code. `attempts` caps guesses per challenge; `consumed_at` stops a
 * verified code being replayed for a second sign-in.
 *
 * Not tied to a `user_id`: the whole point is to verify a mobile number *before* it is
 * known whether an account exists for it, so the challenge is keyed on `mobile` alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 32);
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // Every send/verify looks up the most recent challenge for a mobile.
            $table->index(['mobile', 'created_at']);
        });

        // `mobile` was profile data only; it is now also the broker sign-in key, so two
        // accounts can no longer share one number. Safe as of this migration — checked
        // against the live table first, zero duplicates and zero blanks.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['mobile']);
            $table->unique('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->index('mobile');
        });

        Schema::dropIfExists('otp_codes');
    }
};
