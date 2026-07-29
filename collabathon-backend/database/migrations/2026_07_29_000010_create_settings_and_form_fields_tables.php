<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the admin Settings screen:
 *  - `settings`     key/value store (app accent colour, etc.)
 *  - `form_fields`  which fields the mobile app renders on each form, and whether
 *                   they are required — the toggles on the Settings > Form fields tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 96)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->enum('form', ['broker_registration', 'property_listing']);
            $table->string('field_key', 96);
            $table->string('label');
            $table->boolean('enabled')->default(true);
            $table->boolean('required')->default(false);
            // A required core field cannot be switched off from the UI.
            $table->boolean('is_core')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['form', 'field_key']);
            $table->index(['form', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('settings');
    }
};
