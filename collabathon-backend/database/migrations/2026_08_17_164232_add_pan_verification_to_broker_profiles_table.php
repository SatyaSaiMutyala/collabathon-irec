<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a broker's PAN was checked against Surepass's PAN Comprehensive API at
 * registration, not just typed in — see PanVerificationService. `pan_verified_name`
 * is Surepass's own answer for the cardholder's name, kept alongside `name` on the
 * user record (which the broker typed themselves) so an admin reviewing the
 * application can see the two side by side rather than trusting the typed one blind
 * — same reasoning as the earlier aadhaar_verified* columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->boolean('pan_verified')->default(false)->after('pan_card_path');
            $table->string('pan_verified_name')->nullable()->after('pan_verified');
            $table->timestamp('pan_verified_at')->nullable()->after('pan_verified_name');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn(['pan_verified', 'pan_verified_name', 'pan_verified_at']);
        });
    }
};
