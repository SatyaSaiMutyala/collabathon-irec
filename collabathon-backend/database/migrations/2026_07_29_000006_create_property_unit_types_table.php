<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A property has many unit types (2BHK / 3BHK / Villa / Plot), each with its own areas,
 * price band and floor plan. This is why "Carpet / Built-up / Super built-up" and
 * "Price Range" in the spec cannot be flat columns on `properties`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('label', 64);                    // "3BHK", "Villa", "Studio"
            $table->unsignedInteger('carpet_area_sqft')->nullable();
            $table->unsignedInteger('built_up_area_sqft')->nullable();
            $table->unsignedInteger('super_built_up_area_sqft')->nullable();
            $table->unsignedBigInteger('price_min')->nullable();
            $table->unsignedBigInteger('price_max')->nullable();
            $table->unsignedInteger('units_count')->nullable();
            $table->string('floor_plan_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['property_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_unit_types');
    }
};
