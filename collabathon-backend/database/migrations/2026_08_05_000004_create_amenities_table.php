<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amenities become editable master data instead of a hard-coded list on the controller.
 *
 * Same shape as `unit_types` and for the same reasons: projects store the amenity's
 * *name*, not its id. `property_details.amenities` is a JSON array of strings that the
 * mobile API publishes verbatim and the project sheet renders as chips, so switching to
 * ids would mean rewriting every reader for no gain. AmenityController carries a rename
 * into those arrays in the same transaction, so the two never drift.
 */
return new class extends Migration
{
    /** Replaces PropertyController::AMENITY_OPTIONS, in its original order. */
    private const SEED = [
        'Clubhouse', 'Swimming Pool', 'Gym', "Kids' Play Area", 'Sports Court', 'Garden',
        'Jogging Track', 'Security/CCTV', 'Power Backup', 'Lift', 'Rainwater Harvesting', 'EV Charging',
    ];

    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (self::SEED as $name) {
            DB::table('amenities')->insert([
                'name' => $name,
                'is_active' => true,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Anything already saved on a project that is not in the seed — the intake form has
        // always had a free-text "Other amenities" box, so live records carry names that
        // were never on the checkbox list. They come in switched off: still valid on the
        // projects using them, not offered on new ones until an admin turns them on.
        $seen = collect(self::SEED)->map(fn ($n) => mb_strtolower($n))->all();

        $extras = DB::table('property_details')
            ->whereNotNull('amenities')
            ->pluck('amenities')
            ->flatMap(fn ($json) => json_decode((string) $json, true) ?: [])
            ->filter(fn ($name) => is_string($name) && filled(trim($name)))
            ->map(fn ($name) => trim($name))
            // Case-insensitively unique, first spelling wins: "EV charging" and "EV Charging"
            // are the same amenity, and the unique index would reject the second anyway.
            ->reject(function ($name) use (&$seen) {
                if (in_array(mb_strtolower($name), $seen, true)) {
                    return true;
                }
                $seen[] = mb_strtolower($name);

                return false;
            })
            ->take(500);

        foreach ($extras as $name) {
            DB::table('amenities')->insert([
                'name' => mb_substr($name, 0, 96),
                'is_active' => false,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
