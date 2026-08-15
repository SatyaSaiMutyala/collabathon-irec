<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email-OTP challenge — the channel-partner sign-in this replaces mobile +
 * OTP with (see AuthController::sendEmailOtp/verifyEmailOtp). Same shape as otp_codes,
 * keyed on `email` instead of `mobile`: `code` is hashed like a password, never stored
 * plain; `attempts` caps guesses per challenge; `consumed_at` stops a verified code being
 * replayed for a second sign-in.
 *
 * Not tied to `user_id`: the whole point is to verify an email *before* it is known
 * whether an account exists for it, so the challenge is keyed on the email alone.
 * `users.email` is already unique (see the original users migration), so — unlike
 * otp_codes' own migration — there is no duplicate-cleanup step needed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // Every send/verify looks up the most recent challenge for an email.
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_codes');
    }
};
