<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The long tail of the 69-field property spec. Split 1:1 off `properties` so listing
 * queries never pull these wide text/JSON columns — they are only read on the detail screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();

            // Location context
            $table->json('connectivity_highlights')->nullable();   // metro/airport/highway distances
            $table->json('nearby_infrastructure')->nullable();     // schools, hospitals, malls, IT parks

            // Specifications
            $table->text('construction_specifications')->nullable();
            $table->json('amenities')->nullable();
            $table->string('amenities_size')->nullable();
            $table->unsignedSmallInteger('amenities_count')->nullable();
            $table->string('parking_details')->nullable();

            // Legal
            $table->json('approving_authorities')->nullable();
            $table->json('bank_approvals')->nullable();            // banks offering loans — drives lead financing
            $table->string('legal_due_diligence_path')->nullable();
            $table->json('awards')->nullable();

            // Commercial terms
            $table->json('payment_plan_options')->nullable();
            $table->unsignedBigInteger('booking_amount')->nullable();
            $table->decimal('cp_commission_percent', 5, 2)->nullable();
            $table->text('special_incentives')->nullable();
            $table->text('cashback_schemes')->nullable();
            $table->string('registration_stamp_duty')->nullable();
            $table->string('maintenance_charges')->nullable();
            $table->string('floor_rise')->nullable();
            $table->string('plc_charges')->nullable();
            $table->json('other_charges')->nullable();
            $table->text('payment_schedule')->nullable();

            // Sales
            $table->text('sales_office_address')->nullable();
            $table->string('site_visit_timings')->nullable();
            $table->string('sales_contact_name')->nullable();
            $table->string('sales_contact_number', 32)->nullable();
            $table->text('booking_process')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_details');
    }
};
