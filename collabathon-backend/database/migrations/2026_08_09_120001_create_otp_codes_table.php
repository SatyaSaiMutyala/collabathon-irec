<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per OTP challenge — mobile-number sign-in for channel partners. `code` is
 * hashed the same way a password is, never stored plain, since a leaked row should not
 * hand out a live code. `attempts` caps guesses per challenge; `consumed_at` stops a
 * verified code being replayed for a second sign-in.
 *
 * Not tied to a `user_id`: the whole point is to verify a mobile number *before* it is
 * known whether an account exists for it, so the challenge is keyed on `mobile` alone.
 *
 * ---------------------------------------------------------------------------------
 * Every step below is guarded, because this migration has to survive being re-run after
 * a partial failure.
 *
 * MySQL does not do transactional DDL. When this first ran against a database whose
 * `users.mobile` had duplicates, it got as far as creating `otp_codes` and dropping the
 * old plain index before the UNIQUE failed — and because the migration threw, Laravel
 * recorded nothing as run. The retry then died on "Table 'otp_codes' already exists",
 * and would have died again on a `dropIndex` for an index that was no longer there.
 *
 * So: create only if absent, drop the index only if present, add the unique only if
 * missing. Re-running is then safe from whichever step it stopped at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('otp_codes')) {
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
        }

        /*
         * `mobile` was profile data only; it is now also the broker sign-in key, so two
         * accounts can no longer share one number.
         *
         * Checked here rather than assumed. The original version of this migration
         * asserted "zero duplicates" from a check made against a development database,
         * which said nothing about production — where two accounts did share a number
         * and the deploy failed mid-way. Refusing with the offending numbers named is
         * far better than a raw 1062, and deliberately does not edit anyone's account:
         * which of two real users keeps a phone number is not a migration's decision.
         */
        $duplicates = DB::table('users')
            ->select('mobile')
            ->whereNotNull('mobile')
            ->where('mobile', '<>', '')
            ->groupBy('mobile')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('mobile');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot make users.mobile unique: ' . $duplicates->count()
                . ' number(s) are shared by more than one account ('
                . $duplicates->take(5)->implode(', ')
                . ($duplicates->count() > 5 ? ', …' : '') . '). '
                . 'Clear or correct the duplicates, then run migrate again.'
            );
        }

        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        Schema::table('users', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('users_mobile_index')) {
                $table->dropIndex(['mobile']);
            }

            if (! $indexes->contains('users_mobile_unique')) {
                $table->unique('mobile');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('users'))->pluck('name');

        Schema::table('users', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('users_mobile_unique')) {
                $table->dropUnique(['mobile']);
            }

            if (! $indexes->contains('users_mobile_index')) {
                $table->index('mobile');
            }
        });

        Schema::dropIfExists('otp_codes');
    }
};
