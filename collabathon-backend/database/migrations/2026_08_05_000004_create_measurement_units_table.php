<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Measurement units — the list behind "Project extent metric" on the intake form.
 *
 * Editable master data rather than a hard-coded list, for the same reason project and
 * unit types are: extent is quoted in sq.ft. in one market, sq. yards or guntha in
 * another, and acres for land. Which units a deployment offers is a business decision,
 * not a code change.
 *
 * `properties.extent_metric` stores the unit's *name*, matching how project_type and the
 * unit-row labels work — the mobile API publishes these as strings.
 */
return new class extends Migration
{
    private const SEED = ['Sq.ft.', 'Sq. yards', 'Sq. m.', 'Acres', 'Guntha', 'Cents', 'Hectares'];

    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (self::SEED as $name) {
            DB::table('measurement_units')->insert([
                'name' => $name,
                'is_active' => true,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->string('extent_metric', 96)->nullable()->after('price_per_sqft');
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('extent_metric'));
        Schema::dropIfExists('measurement_units');
    }
};
