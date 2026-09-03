<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which membership category a channel partner falls under — set by the admin, not the
 * broker themselves, so it lives on broker_profiles as a plain nullable column rather
 * than anything collected during registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->enum('member_type', ['HRA', 'NAR', 'Non HRA'])->nullable()->after('is_company');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn('member_type');
        });
    }
};
