<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per device a user has signed in on.
 *
 * Keyed on the token, not on (user, platform): FCM issues a token per app install, one
 * user can be signed in on a phone and a tablet, and the same device can be handed to a
 * different user. `token` unique with an upsert on register means re-registering just
 * moves the row to whoever holds the device now, so a push never follows a device that
 * has been signed out of.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 16)->nullable();  // ios | android
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // The only read this table gets: "every token for these users".
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
