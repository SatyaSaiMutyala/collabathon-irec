<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ~34 fields the mobile RegisterScreen collects, hanging off the broker's user row.
 * Attachments store a disk path, never the file itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Identity
            $table->string('alternate_mobile', 32)->nullable();
            $table->text('residence_address')->nullable();
            $table->string('photo_path')->nullable();

            // Company
            $table->boolean('is_company')->default(false);
            $table->string('company_name')->nullable();
            $table->text('office_address')->nullable();
            $table->string('company_website')->nullable();
            $table->string('social_media_handle')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->unsignedSmallInteger('team_size')->nullable();

            // Statutory documents
            $table->string('pan_card', 32)->nullable();
            $table->string('pan_card_path')->nullable();
            $table->string('aadhaar_card', 32)->nullable();
            $table->string('aadhaar_path')->nullable();
            $table->string('rera_number', 64)->nullable();
            $table->string('rera_certificate_path')->nullable();
            $table->date('rera_certificate_expiry')->nullable();
            $table->string('gst_number', 32)->nullable();
            $table->string('gst_path')->nullable();
            $table->string('cheque_details')->nullable();
            $table->string('cheque_path')->nullable();

            // Coverage
            $table->string('state', 96)->nullable();
            $table->string('city', 96)->nullable();
            $table->json('segments')->nullable();   // Residential / Commercial / Land
            $table->json('zones')->nullable();      // operating localities
            $table->boolean('operates_multiple_states')->default(false);
            $table->text('project_contributions')->nullable();

            // Declaration
            $table->boolean('confirm_accuracy')->default(false);
            $table->string('signature_path')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            // Approval queue is filtered by city/state and ordered by submission date.
            $table->index('city');
            $table->index('state');
            $table->index('rera_number');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_profiles');
    }
};
