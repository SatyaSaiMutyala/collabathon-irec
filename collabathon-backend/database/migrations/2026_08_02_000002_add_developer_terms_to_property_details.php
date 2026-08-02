<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The developer's terms for a project — one per property, shown to brokers and to the
 * developer themselves in the mobile app.
 *
 * Two ways to supply it, because both happen in practice: a signed PDF the developer
 * already has, or terms typed straight in. `terms_type` decides which of the two columns
 * below is the source, so nothing has to guess from which one happens to be populated —
 * a project can legitimately have a stale document *and* newer text after a switch.
 *
 * Kept on property_details rather than property_media: media is a many-row gallery model,
 * and this is exactly one artefact paired 1:1 with the text alternative. It sits beside
 * legal_due_diligence_path, which is the same shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->enum('terms_type', ['document', 'text'])->nullable()->after('cp_commission_percent');
            // What the button in the app is labelled. Falls back to a default when blank,
            // so a project never shows an unnamed link.
            $table->string('terms_title')->nullable()->after('terms_type');
            $table->string('terms_document_path')->nullable()->after('terms_title');
            // longText, not text: pasted terms with formatting run well past 64KB.
            $table->longText('terms_content')->nullable()->after('terms_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('property_details', function (Blueprint $table) {
            $table->dropColumn(['terms_type', 'terms_title', 'terms_document_path', 'terms_content']);
        });
    }
};
