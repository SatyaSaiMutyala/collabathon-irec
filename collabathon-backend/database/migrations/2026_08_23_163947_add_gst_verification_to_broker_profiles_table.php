<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a broker's GSTIN was checked against Surepass's GST Advance API at
 * registration, not just typed in — see GstVerificationService. `gst_verified_name`
 * is Surepass's own answer for the registered legal name, kept alongside
 * `company_name` (which the broker typed themselves) so an admin reviewing the
 * application can see the two side by side rather than trusting the typed one
 * blind — same reasoning as the pan_verified* columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->boolean('gst_verified')->default(false)->after('gst_path');
            $table->string('gst_verified_name')->nullable()->after('gst_verified');
            $table->timestamp('gst_verified_at')->nullable()->after('gst_verified_name');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn(['gst_verified', 'gst_verified_name', 'gst_verified_at']);
        });
    }
};
