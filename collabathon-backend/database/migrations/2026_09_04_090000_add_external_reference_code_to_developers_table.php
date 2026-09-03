<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Master Data (irecexpo.com) registration a developer was converted from, if any
 * — see MasterDataController::convert(). Unique so the same external registration can
 * never be converted twice into two separate developer rows; nullable because most
 * developers are still created directly in the admin panel, not via this import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->string('external_reference_code')->nullable()->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn('external_reference_code');
        });
    }
};
