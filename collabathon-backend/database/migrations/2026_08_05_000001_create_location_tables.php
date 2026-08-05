<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location master data: country -> state -> city.
 *
 * Kept as three tables rather than one self-referencing `locations` row per level: the
 * levels are queried differently (a city dropdown is always scoped to one state, never
 * searched globally), and a self-join would make every such lookup recursive for no gain.
 *
 * Uniqueness is scoped to the parent, not global — "Springfield" may legitimately exist
 * in several states, but not twice in the same one. Deletes cascade downward, so removing
 * a country takes its states and their cities with it; that is the only sane reading of
 * deleting a country, and leaving orphans would surface them in dropdowns with no parent.
 *
 * `developers.city` / `developers.state` stay free-text strings for now — this table set
 * is the source for the pickers, not a foreign key on existing records, so no historical
 * row has to be back-filled or discarded to introduce it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96)->unique();
            // ISO-3166 alpha-2 where the admin knows it. Nullable: the field is a
            // convenience for display, never a lookup key.
            $table->string('code', 8)->nullable();
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name', 96);
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name', 96);
            $table->timestamps();

            $table->unique(['state_id', 'name']);
        });
    }

    public function down(): void
    {
        // Child-first: dropping `states` while `cities` still references it fails on MySQL.
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
