<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One free-text "social media" field meant "put whatever you use" — which in practice
 * meant it only ever held one platform, and the admin panel had nowhere to put a
 * second. Five named fields let a developer list every channel they actually run,
 * and let the UI render each as its own labelled link instead of parsing free text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn('social_media');
        });

        Schema::table('developers', function (Blueprint $table) {
            $table->string('instagram')->nullable()->after('website');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('youtube')->nullable()->after('facebook');
            $table->string('twitter')->nullable()->after('youtube');
            $table->string('linkedin')->nullable()->after('twitter');
        });
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn(['instagram', 'facebook', 'youtube', 'twitter', 'linkedin']);
        });

        Schema::table('developers', function (Blueprint $table) {
            $table->string('social_media')->nullable()->after('website');
        });
    }
};
