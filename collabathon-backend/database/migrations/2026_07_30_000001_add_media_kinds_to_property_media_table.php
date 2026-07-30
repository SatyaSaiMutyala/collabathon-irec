<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two rows of the client's field sheet had no home in `property_media.kind`:
 *   - "Unit Plan/Layout Images"  → distinct from a unit type's own floor plan
 *   - "Payment Schedule"         → listed under Media, i.e. a PDF attachment, whereas
 *                                  `property_details.payment_schedule` only holds prose
 *
 * Widened with `change()` rather than raw ALTER: MySQL renders an enum as a native ENUM
 * and SQLite as a varchar + CHECK constraint, and both have to accept the new kinds or
 * the insert fails on that driver only.
 */
return new class extends Migration
{
    private const KINDS = [
        'image', 'site_layout', 'master_plan', 'brochure', 'price_list',
        'video', 'virtual_tour', 'rera_certificate', 'floor_plan',
        'unit_plan', 'payment_schedule',
    ];

    public function up(): void
    {
        $this->setKinds(self::KINDS);
    }

    public function down(): void
    {
        // Rows using a kind that is about to disappear would violate the narrowed column.
        DB::table('property_media')->whereIn('kind', ['unit_plan', 'payment_schedule'])->delete();

        $this->setKinds(array_slice(self::KINDS, 0, 9));
    }

    private function setKinds(array $kinds): void
    {
        Schema::table('property_media', function (Blueprint $table) use ($kinds) {
            $table->enum('kind', $kinds)->default('image')->change();
        });
    }
};
