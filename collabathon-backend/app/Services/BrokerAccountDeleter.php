<?php

namespace App\Services;

use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes a channel partner's account.
 *
 * Shared because two screens delete the same thing: Approvals (the registration queue)
 * and Channel Partners (the approved roster) are two views of one `users` row, so the
 * operation belongs in one place rather than being written twice and drifting.
 *
 * Distinct from `STATUS_INACTIVE`, which is the *broker's own* self-service delete —
 * that deliberately keeps the row visible in the admin roster (see User's own note).
 * This is the admin's hard delete, matching what Team and Developers already do.
 *
 * The database does the structural work: broker_profiles, uploads, leads, device_tokens
 * and approval_decisions are all `cascadeOnDelete` on users.id, and announcements.sent_by
 * is nullOnDelete. What it cannot do is remove the *files* those rows pointed at, which
 * is the whole reason this class exists — a deleted broker otherwise leaves their PAN,
 * Aadhaar and cheque scans sitting in the bucket forever.
 */
class BrokerAccountDeleter
{
    /** Every file column on broker_profiles — attachments store a path, never the file. */
    private const PROFILE_FILE_COLUMNS = [
        'photo_path',
        'pan_card_path',
        'aadhaar_path',
        'rera_certificate_path',
        'gst_path',
        'cheque_path',
        'signature_path',
    ];

    /** @return string The deleted partner's name, for the caller's flash message. */
    public function delete(User $user): string
    {
        $name = $user->name;

        // Collected before the delete, not after: the uploads rows naming these files
        // are cascaded away with the user, so afterwards there is nothing left to read
        // the paths from.
        $paths = $this->filePathsFor($user);

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        // After the commit. A file removed before it succeeded would be gone from an
        // account that still exists; an orphaned file after a successful delete is the
        // lesser failure, and is recoverable by hand.
        foreach ($paths as $path) {
            FileStorage::delete($path);
        }

        return $name;
    }

    /** @return list<string> */
    private function filePathsFor(User $user): array
    {
        $profile = $user->brokerProfile;

        $fromProfile = $profile
            ? array_map(fn (string $column) => $profile->{$column}, self::PROFILE_FILE_COLUMNS)
            : [];

        return array_values(array_filter(array_merge(
            $fromProfile,
            // Step-3 attachments are stored as Upload rows the moment they are picked,
            // so a draft that never finished still has files to clean up.
            $user->uploads()->pluck('path')->all(),
        )));
    }
}
