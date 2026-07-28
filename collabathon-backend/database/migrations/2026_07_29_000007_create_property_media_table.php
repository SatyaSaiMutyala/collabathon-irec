<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * All property files and links in one polymorphic-by-`kind` table: gallery images,
 * brochures, price lists, master plans, walkthrough links, RERA certificate.
 * `path` is a disk path for uploads; `url` is used for external links (video, 3D tour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->enum('kind', [
                'image', 'site_layout', 'master_plan', 'brochure', 'price_list',
                'video', 'virtual_tour', 'rera_certificate', 'floor_plan',
            ])->default('image');

            $table->string('path')->nullable();     // stored upload
            $table->string('url')->nullable();      // external link
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // Gallery fetch: "all images for this property, in order".
            $table->index(['property_id', 'kind', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_media');
    }
};
