<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INR is the only currency the intake form offers for now, so the column default and the
 * rows already on record have to agree with it — otherwise a property seeded before this
 * change keeps rendering "AED 1.8M" in a UI that can no longer produce AED.
 *
 * The create-properties migration carries the new default too, for databases built from
 * scratch; this one exists for the ones that already ran it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('currency', 8)->default('INR')->change();
        });

        DB::table('properties')->where('currency', '!=', 'INR')->update(['currency' => 'INR']);
    }

    /**
     * Only the default is reversible. Which rows were AED before the backfill is not
     * recorded anywhere, so down() deliberately leaves the data alone rather than
     * guessing every property back to AED.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('currency', 8)->default('AED')->change();
        });
    }
};
