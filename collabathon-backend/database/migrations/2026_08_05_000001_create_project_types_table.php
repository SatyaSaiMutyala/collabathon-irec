<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project types become editable master data instead of a hard-coded enum.
 *
 * `properties.project_type` keeps storing the type's *name* rather than a foreign key:
 * the mobile API already publishes it as a string (PropertyResource), the list filters
 * query it as a string, and swapping to an id would break both for no gain at this size.
 * ProjectTypeController renames existing projects in the same transaction as the type,
 * so the two never drift, and refuses to delete a type still in use.
 *
 * `requires_possession_date` drives the conditional "Possession date (as per RERA)"
 * field — RERA only mandates a completion date for built units, so land-only types like
 * Plotted Development do not ask for one.
 */
return new class extends Migration
{
    /** The enum's values, carried over so existing projects keep validating. */
    private const SEED = [
        // name => requires a RERA possession date
        'Residential' => true,
        'Commercial' => true,
        'Mixed-use' => true,
        'Villa' => true,
        'Row House' => true,
        'Plotted Development' => false,   // land, not a built unit
    ];

    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96)->unique();
            $table->boolean('requires_possession_date')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (self::SEED as $name => $requiresPossession) {
            DB::table('project_types')->insert([
                'name' => $name,
                'requires_possession_date' => $requiresPossession,
                'is_active' => true,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Was an enum, so any type added from the settings screen would have been
        // rejected by the database itself.
        Schema::table('properties', function (Blueprint $table) {
            $table->string('project_type', 96)->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('project_type', array_keys(self::SEED))->change();
        });

        Schema::dropIfExists('project_types');
    }
};
