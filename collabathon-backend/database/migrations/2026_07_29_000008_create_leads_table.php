<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (broker, property) pair — the broker's relationship with a listing as it
 * moves viewed → interested → accepted/declined.
 *
 * `contact_unlocked` encodes the platform's core privacy rule: a developer only sees the
 * broker's phone/email once the broker marks "Interested". A view never exposes contact.
 *
 * `developer_id` is denormalised from the property so a developer's lead list is a single
 * indexed lookup instead of a join through properties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('developer_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['viewed', 'interested', 'accepted', 'declined'])->default('viewed');
            $table->boolean('contact_unlocked')->default(false);

            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('interested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('developer_note')->nullable();

            $table->timestamps();

            // A broker has exactly one lead row per property; re-viewing updates it.
            $table->unique(['property_id', 'broker_id']);

            // Developer inbox, property drill-down, broker's own list, admin activity feed.
            $table->index(['developer_id', 'status', 'created_at']);
            $table->index(['property_id', 'status']);
            $table->index(['broker_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
