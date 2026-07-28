<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core property row: only the columns that get filtered, sorted or listed live here.
 * The long tail of the 69-field spec sits in `property_details`, unit pricing in
 * `property_unit_types`, and files in `property_media` — so a list query never drags
 * wide text columns across the wire.
 *
 * `developer_id` is NOT NULL: a property always belongs to exactly one developer.
 *
 * `views_count` / `interests_count` are denormalised counters. At 8k concurrent users a
 * COUNT(*) subquery per row on every listing page is the thing that falls over first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('project_type', ['Residential', 'Commercial', 'Mixed-use', 'Plotted Development', 'Villa', 'Row House'])
                ->default('Residential');
            $table->enum('project_status', ['New Launch', 'Under Construction', 'Ready to Move', 'Nearing Completion'])
                ->default('New Launch');
            // Publish state, independent of construction stage.
            $table->enum('listing_status', ['draft', 'active', 'archived'])->default('draft');

            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();

            // RERA
            $table->string('rera_number', 64)->nullable();
            $table->date('rera_registered_at')->nullable();
            $table->date('rera_valid_till')->nullable();

            // Location
            $table->string('state', 96)->nullable();
            $table->string('city', 96)->nullable();
            $table->string('locality', 128)->nullable();
            $table->text('full_address')->nullable();
            $table->string('landmark')->nullable();
            $table->string('pincode', 12)->nullable();
            $table->enum('zone', ['East', 'West', 'North', 'South', 'Central'])->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('maps_link')->nullable();

            // Pricing — range is what the listing filters on
            $table->unsignedBigInteger('price_min')->nullable();
            $table->unsignedBigInteger('price_max')->nullable();
            $table->unsignedInteger('price_per_sqft')->nullable();
            $table->string('currency', 8)->default('AED');

            // Scale
            $table->unsignedInteger('total_units')->nullable();
            $table->unsignedSmallInteger('towers')->nullable();
            $table->unsignedSmallInteger('floors_per_tower')->nullable();
            $table->unsignedSmallInteger('flats_per_floor')->nullable();
            $table->decimal('land_parcel_acres', 10, 2)->nullable();
            $table->unsignedBigInteger('total_project_area_sqft')->nullable();
            $table->unsignedTinyInteger('open_space_percent')->nullable();

            // Timeline
            $table->date('launch_date')->nullable();
            $table->date('possession_date')->nullable();
            $table->unsignedTinyInteger('construction_progress')->default(0);

            $table->string('green_certification', 64)->nullable();
            $table->boolean('vastu_compliant')->default(false);

            // Denormalised counters — see class docblock
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('interests_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Listing queries: "active properties, newest first", optionally by developer.
            $table->index(['listing_status', 'created_at']);
            $table->index(['developer_id', 'listing_status']);
            $table->index(['city', 'listing_status']);
            $table->index(['project_type', 'listing_status']);
            $table->index('price_min');
            $table->index('price_max');
            $table->index('possession_date');
        });

        // LIKE '%term%' cannot use a B-tree index. FULLTEXT is what makes search hold
        // up at this scale; the query layer falls back to LIKE only for short terms.
        // Guarded by driver so the suite can still run on SQLite.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE properties ADD FULLTEXT properties_search_ft (name, locality, city)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
