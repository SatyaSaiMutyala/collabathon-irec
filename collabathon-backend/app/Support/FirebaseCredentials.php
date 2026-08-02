<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * The Firebase service account, managed from the admin panel instead of over SSH.
 *
 * The file is gitignored on purpose — a private key that can send as the whole project
 * must not live in the repo — which means it does not travel with a deploy. Uploading it
 * here is the alternative to shelling into every environment and scp-ing it in.
 *
 * It is written under storage/app, never under public/: nothing here is servable, and
 * the contents are never rendered back to the browser. The panel shows only the project
 * id and the client email, which identify the account without being usable as one.
 */
class FirebaseCredentials
{
    /** The three keys that make a service account JSON actually a service account. */
    private const REQUIRED = ['project_id', 'client_email', 'private_key'];

    public static function path(): string
    {
        return storage_path(config('services.fcm.credentials'));
    }

    public static function isConfigured(): bool
    {
        return static::read() !== null;
    }

    public static function projectId(): ?string
    {
        return static::read()['project_id'] ?? null;
    }

    public static function clientEmail(): ?string
    {
        return static::read()['client_email'] ?? null;
    }

    /** So the panel can say how stale the current key is without exposing it. */
    public static function uploadedAt(): ?int
    {
        $path = static::path();

        return is_readable($path) ? (filemtime($path) ?: null) : null;
    }

    /**
     * Validates and stores an uploaded service account.
     *
     * @return string|null  an error message, or null on success
     */
    public static function store(UploadedFile $file): ?string
    {
        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);

        if (! is_array($decoded)) {
            return 'That file is not valid JSON.';
        }

        foreach (self::REQUIRED as $key) {
            if (blank($decoded[$key] ?? null)) {
                return "That JSON is missing \"{$key}\" — download the *service account* key "
                    . 'from Project Settings → Service accounts, not google-services.json.';
            }
        }

        if (($decoded['type'] ?? null) !== 'service_account') {
            return 'That JSON is not a service account key.';
        }

        // Catches a key that is truncated or wrapped wrong before it becomes a runtime
        // signing failure inside a request that was trying to notify someone.
        if (openssl_pkey_get_private($decoded['private_key']) === false) {
            return 'The private key in that file could not be read — it may be truncated.';
        }

        $path = static::path();
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            return 'Could not create ' . dirname($path) . ' — check filesystem permissions.';
        }

        // Written via a temp file in the same directory, then renamed: rename is atomic,
        // so a request landing mid-write reads either the old key or the new one, never
        // half a file.
        $temp = $path . '.' . bin2hex(random_bytes(4));
        if (file_put_contents($temp, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            return 'Could not write to ' . $path . ' — check filesystem permissions.';
        }

        chmod($temp, 0600);
        rename($temp, $path);

        static::invalidate();

        return null;
    }

    public static function forget(): void
    {
        $path = static::path();
        if (file_exists($path)) {
            unlink($path);
        }

        static::invalidate();
    }

    /**
     * The cached OAuth2 token was minted from the *previous* key and stays valid for the
     * rest of its hour. Dropping it is what makes a replaced key take effect now rather
     * than up to 55 minutes later.
     */
    private static function invalidate(): void
    {
        Cache::forget('fcm.access_token');
    }

    /** @return array<string,mixed>|null */
    private static function read(): ?array
    {
        $path = static::path();

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        foreach (self::REQUIRED as $key) {
            if (! is_array($decoded) || blank($decoded[$key] ?? null)) {
                return null;
            }
        }

        return $decoded;
    }
}
