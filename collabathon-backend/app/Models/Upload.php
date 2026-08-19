<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file uploaded through the generic `/uploads` endpoint — see the migration's docblock. */
#[Fillable(['user_id', 'type', 'disk', 'path', 'original_name', 'size'])]
class Upload extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
