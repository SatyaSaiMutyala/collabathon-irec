<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which identity a broker actually proved with an OTP at registration — `email` or
 * `mobile`, whichever `cp_login_method` (Settings) routed them through at the time.
 *
 * The other one is only ever self-typed, never verified, which is why
 * CompleteProfileScreen locks the proven field and leaves the other editable: without
 * this column that decision could only be made from the route params a fresh OTP
 * verification carries (`mobileParam`/`emailParam`), which do not exist any more once
 * a draft session resumes on app reopen — the screen would have no way to tell which
 * field was ever proven, and either lock both or lock neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->enum('verified_channel', ['email', 'mobile'])->nullable()->after('registration_step');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn('verified_channel');
        });
    }
};
