<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Developer companies. Admin creates these — developers never self-register — so
 * `user_id` is the login account issued alongside the company record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 96)->nullable();
            $table->string('state', 96)->nullable();
            $table->string('rera_number', 64)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('about')->nullable();

            // Commission paid to the channel partner on a closed lead.
            $table->decimal('cp_payout_percent', 5, 2)->default(0);
            $table->boolean('verified')->default(false);
            $table->enum('status', ['active', 'paused'])->default('active');

            $table->timestamps();

            // Admin list filters on status/city and sorts by name or recency.
            $table->index(['status', 'created_at']);
            $table->index('city');
            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developers');
    }
};
