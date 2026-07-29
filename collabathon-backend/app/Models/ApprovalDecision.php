<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail: who approved/rejected a broker, when and why. */
#[Fillable(['user_id', 'decided_by', 'decision', 'reason', 'internal_note'])]
class ApprovalDecision extends Model
{
    public function broker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
