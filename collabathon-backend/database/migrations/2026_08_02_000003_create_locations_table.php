<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Country on a property.
 *
 * State and city were already stored as plain text here; country was implied by them and
 * never recorded, which left the project form unable to offer the country → state → city
 * cascade the Locations settings screen manages.
 *
 * The names are denormalised onto the row on purpose, matching `state` and `city`: a
 * project keeps the location it was published with even if that city is later renamed or
 * removed from the master list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('country', 96)->nullable()->after('zone');
        });

        // Existing rows carry their emirate in `state`, so the country follows from it.
        DB::table('properties')->where('state', 'UAE')->update(['country' => 'United Arab Emirates']);
        DB::table('properties')->whereNull('country')->whereNotNull('state')->update(['country' => 'India']);
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};
