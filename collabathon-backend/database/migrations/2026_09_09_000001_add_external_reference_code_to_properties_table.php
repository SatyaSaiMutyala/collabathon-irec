<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Master Data (irecexpo.com) registration a listing was imported from, if any —
 * the listing-side twin of `developers.external_reference_code`.
 *
 * The developer column alone could not carry this. A reference code identifies one
 * *registration*, and the same developer files a fresh registration for every project
 * they launch, so the second registration's code never matches the developer created
 * from the first. Recording it here is what lets a repeat import resolve to "this
 * project is already in" rather than either erroring or quietly creating a duplicate.
 *
 * Unique for that reason; nullable because listings created in the admin panel — still
 * the normal case — have no external registration behind them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('external_reference_code')->nullable()->unique()->after('developer_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('external_reference_code');
        });
    }
};
