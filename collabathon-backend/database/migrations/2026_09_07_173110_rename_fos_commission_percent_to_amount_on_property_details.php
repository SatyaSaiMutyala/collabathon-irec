<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FOS commission turned out to be a flat payout amount an admin types in (e.g. 25000),
 * not a percentage — `decimal(5,2)` topped out at 999.99, which a real value like that
 * overflowed, and the field's own validation was rejecting anything over 100 outright.
 * Renamed to `_amount` so the column itself no longer claims to be a percentage, and
 * widened well past any realistic commission payout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->renameColumn('fos_commission_percent', 'fos_commission_amount');
        });

        Schema::table('property_details', function (Blueprint $table) {
            $table->decimal('fos_commission_amount', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->decimal('fos_commission_amount', 5, 2)->nullable()->change();
        });

        Schema::table('property_details', function (Blueprint $table) {
            $table->renameColumn('fos_commission_amount', 'fos_commission_percent');
        });
    }
};
