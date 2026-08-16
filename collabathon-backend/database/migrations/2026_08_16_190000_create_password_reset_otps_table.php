<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per admin-panel "forgot password" challenge — see PasswordResetController.
 *
 * Same shape as email_otp_codes, and deliberately a separate table rather than a reuse
 * of it: that one issues a fixed code so a store reviewer can always get through the
 * mobile sign-in (see EmailOtpCode), which is exactly the property a password reset for
 * an admin account must not have. Keeping them apart means the mobile flow can stay
 * convenient without that decision leaking into panel credentials.
 *
 * Laravel's stock `password_reset_tokens` is left alone: it is keyed one-row-per-email
 * with no attempt counter, and a short numeric code needs guess-capping the way a 60-character
 * signed token does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
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
        Schema::dropIfExists('password_reset_otps');
    }
};
