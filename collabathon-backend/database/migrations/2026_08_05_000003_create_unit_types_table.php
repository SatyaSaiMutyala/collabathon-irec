<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unit types become editable master data instead of a hard-coded list in the Blade.
 *
 * Named `unit_types` to sit alongside `property_unit_types`, which is a different thing:
 * this is the catalogue an admin curates, that one is the per-project rows (areas, price
 * band, floor plan) that reference a label from it.
 *
 * `property_unit_types.label` keeps storing the *name*, matching how project_types works
 * and for the same reasons — the mobile API publishes it as a string and the unit tables
 * read it as one. UnitTypeController renames existing rows in the same transaction as the
 * type, so the two never drift.
 */
return new class extends Migration
{
    /** Replaces the array that was inlined in the intake form. */
    private const SEED = ['2BHK', '3BHK', '4BHK', '5BHK', '5+BHK', 'Villa', 'Plot', 'Studio'];

    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (self::SEED as $name) {
            DB::table('unit_types')->insert([
                'name' => $name,
                'is_active' => true,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Labels already saved against projects that are not in the seed — 1BHK and
        // Commercial unit were on the old list. Carried in as inactive: they stay valid
        // on the projects using them without being offered on new ones.
        $existing = DB::table('property_unit_types')
            ->select('label')->distinct()->pluck('label')
            ->filter()
            ->reject(fn ($label) => in_array($label, self::SEED, true));

        foreach ($existing as $name) {
            DB::table('unit_types')->insert([
                'name' => $name,
                'is_active' => false,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
