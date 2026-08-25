<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one place that knows where an uploaded file lives and how to link to it.
 *
 * Two disks, split by what the file *is* rather than where it came from:
 *
 *   uploads — property photos/plans/brochures, developer logos, CP profile photos.
 *             Public objects; the URL is stable and cacheable.
 *   secure  — the KYC set: PAN, Aadhaar (scan and XML), RERA certificate, GST,
 *             cancelled cheque, signature. Private objects, reached only through a
 *             short-lived signed URL.
 *
 * Which disk a path belongs to is decided from its prefix (see SECURE_PREFIXES), not
 * from a column, so the thousands of paths already in the database keep working with
 * no migration. That is also why prefixes must not be renamed casually: the prefix is
 * the permission.
 *
 * Every caller goes through here rather than naming a disk. Before this existed, 24
 * places hardcoded `'public'` and 25 more built URLs with `asset('storage/'.$path)`,
 * which meant flipping FILESYSTEM_DISK to s3 changed nothing at all — uploads still
 * went to the server's own disk and every URL still pointed at it.
 */
class FileStorage
{
    /** Anything under these is private. Everything else is public. */
    private const SECURE_PREFIXES = ['broker-documents/'];

    /**
     * How long a signed link to a KYC document stays valid.
     *
     * This is the lifetime of the *link*, never of the document. The object itself is
     * kept indefinitely — there is no lifecycle rule on the bucket and nothing deletes
     * it — and a fresh link is minted every time a page or screen asks for one, so an
     * admin can open a partner's PAN card years after it was uploaded.
     *
     * Seven days is the ceiling: AWS rejects a SigV4 presigned URL dated further out
     * than one week ("the expiration date ... must be less than one week"), so a link
     * that never expires is not something S3 offers for a private object. The only way
     * to get a permanent address is to make the object public, and a permanently
     * public Aadhaar scan is exactly what the private disk exists to prevent.
     *
     * The window is set at that ceiling rather than at an hour because the mobile app
     * persists the profile response to AsyncStorage and restores it on relaunch. A
     * shorter window would mean a channel partner who reopens the app after a while —
     * or opens it with no signal, before the profile can refresh — taps their own PAN
     * card and gets an S3 error page. A week outlives any realistic gap between the
     * response being cached and the document being tapped.
     */
    private const SIGNED_URL_MINUTES = 7 * 24 * 60;

    public static function isSecure(string $path): bool
    {
        return Str::startsWith(ltrim($path, '/'), self::SECURE_PREFIXES);
    }

    /** The disk a given stored path lives on. */
    public static function diskFor(string $path): Filesystem
    {
        return Storage::disk(self::isSecure($path) ? 'secure' : 'uploads');
    }

    /** The disk a *new* file should be written to, chosen from its destination folder. */
    public static function diskForFolder(string $folder): Filesystem
    {
        return self::diskFor(rtrim($folder, '/') . '/');
    }

    /**
     * Store an upload and return its path.
     *
     * Visibility is set by the disk, not per call: `uploads` is configured public and
     * `secure` private, so a KYC document cannot be made world-readable by a caller
     * passing the wrong argument.
     */
    public static function put(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, self::diskName($folder));
    }

    /**
     * A link the browser or app can actually open.
     *
     * Public files get their permanent URL. KYC documents get a signed one that expires
     * — which is the whole reason they are on a separate disk, so this is not optional
     * and there is no public-URL path for them to fall back to.
     */
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return self::isSecure($path)
            ? self::temporaryUrl($path)
            : self::diskFor($path)->url($path);
    }

    /** A time-limited link to a private object. */
    public static function temporaryUrl(string $path, ?int $minutes = null): ?string
    {
        $disk = self::diskFor($path);

        // The local driver cannot sign URLs. Development stores both kinds under the
        // same public root (see config/filesystems.php), so fall back to the plain URL
        // there rather than failing — the signing boundary only exists on S3.
        try {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes ?? self::SIGNED_URL_MINUTES));
        } catch (\RuntimeException) {
            return $disk->url($path);
        }
    }

    public static function exists(?string $path): bool
    {
        return filled($path) && self::diskFor($path)->exists($path);
    }

    public static function get(string $path): ?string
    {
        return self::diskFor($path)->get($path);
    }

    /** Deleting a path that is already gone is not an error worth propagating. */
    public static function delete(?string $path): void
    {
        if (filled($path)) {
            self::diskFor($path)->delete($path);
        }
    }

    /**
     * Which disk a destination folder belongs to.
     *
     * Public because the call sites pass it straight to Laravel's own
     * `UploadedFile::store($folder, $disk)` — keeping the framework method rather
     * than wrapping it means the surrounding code reads the same as before, with
     * only the disk argument now derived instead of hardcoded to 'public'.
     */
    public static function diskName(string $folder): string
    {
        return self::isSecure(rtrim($folder, '/') . '/') ? 'secure' : 'uploads';
    }
}
