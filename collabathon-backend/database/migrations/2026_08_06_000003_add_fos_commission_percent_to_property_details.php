<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FOS ("Feet on Street") commission — the payout for on-ground field sales agents,
 * separate from the CP (channel partner) commission it sits beside on the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->decimal('fos_commission_percent', 5, 2)->nullable()->after('cp_commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->dropColumn('fos_commission_percent');
        });
    }
};
