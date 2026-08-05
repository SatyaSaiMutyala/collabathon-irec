<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Same split as developers.social_media — see that migration's docblock. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn('social_media_handle');
        });

        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->string('instagram')->nullable()->after('company_website');
            $table->string('facebook')->nullable()->after('instagram');
            $table->string('youtube')->nullable()->after('facebook');
            $table->string('twitter')->nullable()->after('youtube');
            $table->string('linkedin')->nullable()->after('twitter');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn(['instagram', 'facebook', 'youtube', 'twitter', 'linkedin']);
        });

        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->string('social_media_handle')->nullable()->after('company_website');
        });
    }
};
