<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per file uploaded through the generic `/uploads` endpoint — a PAN/Aadhaar/
 * RERA/GST scan picked on Complete Profile's step 3, stored the moment it's picked
 * rather than held in memory until the whole form submits. `saveRegistrationStep`
 * links a `*_path` it's handed back to `broker_profiles` only after confirming the
 * row here belongs to the caller (see AuthController::linkUploadedPath) — this table
 * is what makes that check possible instead of trusting a path string on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // What the file is for — 'pan_card', 'aadhaar', 'rera_certificate', 'gst',
            // 'photo', etc. Not a foreign key to any one form: the same endpoint is
            // meant to be reused wherever an upload-then-link flow makes sense next.
            $table->string('type', 32);
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            // The only read this table gets: "does this path belong to this user".
            $table->index(['user_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
