<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Connection details for the irecexpo.com "Master Data" API — the feed of developer/
 * project registrations an admin can browse and convert into real Developer accounts.
 * Same reasoning as WhatsAppSettings/SurepassSettings: a key that can pull another
 * organisation's data belongs encrypted in `settings`, not in `.env` or committed code.
 */
class MasterDataSettings
{
    public const KEY_BASE_URL = 'master_data_base_url';
    public const KEY_API_KEY = 'master_data_api_key';

    /** The vendor never publishes a sandbox — this is the only endpoint that exists. */
    public const DEFAULT_BASE_URL = 'https://irecexpo.com/data/api_collabathon.php';

    public static function baseUrl(): string
    {
        return Setting::get(self::KEY_BASE_URL) ?: self::DEFAULT_BASE_URL;
    }

    public static function apiKey(): ?string
    {
        $stored = Setting::get(self::KEY_API_KEY);

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // A rotated APP_KEY makes every stored secret undecryptable — same failure
            // mode as WhatsAppSettings, handled the same way: log it, let the settings
            // screen show "not configured" rather than fail the request that asked.
            Log::warning('Stored Master Data API key could not be decrypted — re-enter it in Settings.');

            return null;
        }
    }

    public static function isConfigured(): bool
    {
        return filled(self::apiKey());
    }

    public static function putApiKey(string $key): void
    {
        Setting::put(self::KEY_API_KEY, Crypt::encryptString($key));
    }

    public static function putBaseUrl(string $url): void
    {
        Setting::put(self::KEY_BASE_URL, $url);
    }

    /** Masked for display — enough to confirm which key is saved, not enough to reuse. */
    public static function masked(): ?string
    {
        $key = self::apiKey();

        if (! $key) {
            return null;
        }

        return mb_strlen($key) <= 12
            ? str_repeat('•', mb_strlen($key))
            : mb_substr($key, 0, 8) . str_repeat('•', 10) . mb_substr($key, -4);
    }
}
