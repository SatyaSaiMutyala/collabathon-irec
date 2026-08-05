<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two contacts per developer, plus the location detail the admin form now collects.
 *
 * The contacts are deliberately separate columns rather than a `developer_contacts` table:
 * there are exactly two, they mean different things, and only one of them is ever
 * published. A generic contacts table would need a `visible_to_cp` flag on every row and
 * would let a third contact appear that no screen knows how to place.
 *
 *   contact_person      — the developer's public point of contact. Already existed, and is
 *                         what DeveloperResource sends to the mobile app.
 *   key_contact_person  — the internal relationship owner. Admin-only: it must never reach
 *                         a channel partner, so it is left out of DeveloperResource.
 *
 * Geo-fence: pincode, address and lat/lng are all stored, because the source varies — an
 * admin may know only the pincode, may paste an address, or may drop a pin. Whichever is
 * filled, the others can be resolved from it later; storing one shape only would throw
 * away what was actually entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            // Contacts
            $table->string('contact_designation', 96)->nullable()->after('contact_person');
            $table->string('key_contact_person')->nullable()->after('contact_designation');
            $table->string('key_contact_designation', 96)->nullable()->after('key_contact_person');
            $table->string('key_contact_mobile', 32)->nullable()->after('key_contact_designation');
            $table->string('key_contact_email')->nullable()->after('key_contact_mobile');

            // Location — country sits above the existing state/city pair.
            $table->string('country', 96)->nullable()->after('company_name');
            $table->string('pincode', 12)->nullable()->after('city');
            $table->text('address')->nullable()->after('pincode');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Presence
            $table->string('website')->nullable()->after('about');
            $table->string('social_media')->nullable()->after('website');

            // The admin list already filters on city; pincode is the other lookup an admin
            // reaches for when two developers share a city name.
            $table->index('pincode');
        });
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropIndex(['pincode']);
            $table->dropColumn([
                'contact_designation',
                'key_contact_person',
                'key_contact_designation',
                'key_contact_mobile',
                'key_contact_email',
                'country',
                'pincode',
                'address',
                'latitude',
                'longitude',
                'website',
                'social_media',
            ]);
        });
    }
};
