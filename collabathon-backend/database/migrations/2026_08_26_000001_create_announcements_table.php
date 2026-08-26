<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of every manual push an admin sends.
 *
 * Until now these were fire-and-forget: AnnouncementController handed the title and body
 * straight to FCM and kept nothing, so there was no way to answer "what did we send, to
 * whom, and did it arrive". The delivery counts are stored alongside the message because
 * they are only knowable at send time — FCM reports per-token success on that request and
 * nowhere else.
 *
 * Lifecycle pushes (approvals, lead updates) are deliberately NOT recorded here: each one
 * already has a domain record behind it, and the in-app Notifications screen derives from
 * those. This table is only for the sends that have nothing else to point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 60);
            $table->string('body', 180);
            $table->string('image_path')->nullable();
            $table->enum('audience', ['brokers', 'developers', 'everyone']);

            // Who sent it. Nulled rather than cascaded — losing the admin account should
            // not erase the record that the message went out.
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
