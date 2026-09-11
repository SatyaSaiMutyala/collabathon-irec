<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an admin has pinned this developer in the channel partner's directory: rank 1
 * is shown first, then 2, then every unpinned developer in its normal order.
 *
 * One column on `developers` rather than a developer x city table, because a developer
 * row carries exactly one `city` and the broker Home screen filters the directory on
 * that same column — so a rank here already *is* a rank within that developer's own
 * city, and a second city's rank could never be reached to mean anything.
 *
 * Nullable with no default: "not pinned" has to stay distinguishable from "pinned
 * last", and a default of 0 would have made every developer created before this
 * migration look deliberately ranked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')->nullable()->after('cp_payout_percent');

            // The broker directory's query exactly: status = active, city = ?, ordered by
            // priority. Leading with the two equality columns is what lets the engine read
            // the rank order straight off the index instead of filesorting the city's rows.
            $table->index(['status', 'city', 'priority'], 'developers_status_city_priority_index');
        });
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropIndex('developers_status_city_priority_index');
            $table->dropColumn('priority');
        });
    }
};
