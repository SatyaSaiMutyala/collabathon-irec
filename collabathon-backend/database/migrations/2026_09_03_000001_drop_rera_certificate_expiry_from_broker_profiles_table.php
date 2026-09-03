<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the RERA certificate expiry date.
 *
 * Nothing has collected it for a while: the registration wizard asks for the RERA
 * number and the certificate itself, the admin create/edit form never had the field,
 * and the only writer left was a column on the bulk-import sheet feeding a value no
 * screen would show back. The approval page still rendered it, which is how a partner
 * ended up flagged "expired" on a date nobody had entered or could correct.
 *
 * The dates already stored go with it — deliberately, and irreversibly. `down()`
 * restores the column so the schema can be rolled back, but not the values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->dropColumn('rera_certificate_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('broker_profiles', function (Blueprint $table) {
            $table->date('rera_certificate_expiry')->nullable()->after('rera_certificate_path');
        });
    }
};
