<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which way a configuration faces — a standard line item on an Indian listing, and one
 * of the first things a broker is asked about.
 *
 * Stored as a short string rather than an enum: the eight compass points are a closed
 * set today, but an enum change is a table rebuild, and the value is validated against
 * PropertyUnitType::FACINGS on the way in anyway. Nullable because every row that
 * already exists has no answer, and a project may legitimately not quote one.
 *
 * Not indexed. Nothing filters or sorts on it yet; add one alongside the query that
 * first needs it rather than paying for it on every write until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_unit_types', function (Blueprint $table) {
            $table->string('facing', 16)->nullable()->after('super_built_up_area_sqft');
        });
    }

    public function down(): void
    {
        Schema::table('property_unit_types', function (Blueprint $table) {
            $table->dropColumn('facing');
        });
    }
};
