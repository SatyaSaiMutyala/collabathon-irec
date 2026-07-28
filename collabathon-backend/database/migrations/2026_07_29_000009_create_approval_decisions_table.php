<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for broker approvals. `users.status` holds the current state; this table
 * holds who decided what, when and why — so a rejection reason survives a later re-approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // the broker
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('decision', ['approved', 'rejected']);
            $table->text('reason')->nullable();
            $table->text('internal_note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['decision', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
