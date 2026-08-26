<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\FileStorage;

/** One manual push an admin sent — see the migration for what is and isn't recorded here. */
#[Fillable([
    'title', 'body', 'image_path', 'audience',
    'sent_by', 'recipients', 'sent_count', 'failed_count',
])]
class Announcement extends Model
{
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Absolute, because FCM fetches it from Google's servers, not from the device.
     *
     * Through FileStorage like every other upload, so this follows FILESYSTEM_DISK to S3
     * instead of pinning itself to the server's own disk — see that class's note on the
     * 24 hardcoded 'public' call sites that made flipping the disk a no-op.
     */
    public function imageUrl(): ?string
    {
        return FileStorage::url($this->image_path);
    }

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            'brokers' => 'Channel partners',
            'developers' => 'Developers',
            default => 'Everyone',
        };
    }
}
