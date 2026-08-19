<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Google Maps API key the mobile app's "Choose from Map" location picker needs —
 * configured from the admin panel rather than committed into the mobile repo, same
 * reasoning as {@see SurepassSettings}: kept out of source control so it can be
 * rotated without a code change, and encrypted at rest so a plain database dump/backup
 * doesn't hand it out either.
 *
 * Unlike a typical server-side secret, a Maps API key is *meant* to ship inside a
 * client app — Google's own model is to restrict it by package name/SHA-1 fingerprint
 * (Android) or bundle ID (iOS), not keep it hidden — so serving it from the public
 * /config endpoint is correct, not a leak.
 *
 * Android specifically cannot pick this up at JS runtime the way the rest of /config
 * works: the native Google Maps SDK reads its key from a compiled AndroidManifest.xml
 * entry at process start, before any JS runs. Saving a new key here still requires
 * copying it into the native Android build config and rebuilding — this class removes
 * "where does the current key live" as a question, not "does changing it need a
 * rebuild" as a fact. iOS needs no key at all (react-native-maps defaults to Apple
 * Maps there).
 */
class GoogleMapsSettings
{
    private const KEY = 'google_maps_api_key';

    public static function get(): ?string
    {
        $stored = Setting::get(self::KEY);

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // A rotated APP_KEY makes every stored secret undecryptable — same failure
            // mode as SurepassSettings/MailSettings, handled the same way: log it, let
            // the settings screen show "not configured" rather than fail the request.
            Log::warning('Stored Google Maps API key could not be decrypted — re-enter it in Settings.');

            return null;
        }
    }

    public static function put(string $key): void
    {
        Setting::put(self::KEY, Crypt::encryptString($key));
    }

    public static function isConfigured(): bool
    {
        return filled(self::get());
    }

    /** Masked for display — enough to confirm which key is saved, not enough to reuse. */
    public static function masked(): ?string
    {
        $key = self::get();

        if (! $key) {
            return null;
        }

        return mb_strlen($key) <= 12
            ? str_repeat('•', mb_strlen($key))
            : mb_substr($key, 0, 6) . str_repeat('•', 10) . mb_substr($key, -6);
    }
}
