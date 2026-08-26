<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `body` was capped at 180 characters on the assumption that a push notification only
 * ever needs to read on its own, unexpanded, in the OS notification shade. In practice
 * an admin wants to write a fuller description — the in-app Notifications list already
 * clamps this to 2 lines (see NotificationRow in NotificationsScreen.js) and the detail
 * screen shows it in full, so there is nowhere left this needed a hard 180-char ceiling.
 * The OS notification tray still truncates its own preview the same way regardless of
 * what's stored — that was never this column's job to enforce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->text('body')->change();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('body', 180)->change();
        });
    }
};
