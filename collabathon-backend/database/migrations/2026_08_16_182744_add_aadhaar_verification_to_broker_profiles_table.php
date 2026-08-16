<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a broker's Aadhaar was checked against Surepass at registration, not just
 * typed in — see AadhaarVerificationService. `aadhaar_verified_name` is Surepass's own
 * answer for the cardholder's name, kept alongside `name` on the user record (which
 * the broker typed themselves) so an admin reviewing the application can see the two
 * side by side rather than trusting the typed one blind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->boolean('aadhaar_verified')->default(false)->after('aadhaar_path');
            $table->string('aadhaar_verified_name')->nullable()->after('aadhaar_verified');
            $table->timestamp('aadhaar_verified_at')->nullable()->after('aadhaar_verified_name');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn(['aadhaar_verified', 'aadhaar_verified_name', 'aadhaar_verified_at']);
        });
    }
};
