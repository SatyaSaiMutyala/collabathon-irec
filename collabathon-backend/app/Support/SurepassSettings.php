<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * KYC verification (GST / Aadhaar offline XML-QR / PAN), configured from the admin
 * panel — same reasoning as {@see MailSettings}: a Bearer token that can pull identity
 * data on this organisation's behalf belongs encrypted in `settings`, not in `.env`.
 *
 * Sandbox and production are two independent tokens, not one token pointed at two
 * hosts — Surepass issues them separately (see the console's own Sandbox/Production
 * toggle), and keeping both stored lets `environment()` switch which one is active
 * without either being lost. Build and test against sandbox; flip `KEY_ENVIRONMENT`
 * to production only once that works — see the KYC provider setup doc in docs/.
 */
class SurepassSettings
{
    public const KEY_SANDBOX_TOKEN = 'surepass_sandbox_token';
    public const KEY_PRODUCTION_TOKEN = 'surepass_production_token';
    public const KEY_ENVIRONMENT = 'surepass_environment';

    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    // From the Surepass docs' own "Technical Implementation" section, repeated across
    // every endpoint page — the two environments are genuinely different hosts, not
    // the same host with a different token.
    private const BASE_URL_SANDBOX = 'https://sandbox.surepass.io';
    private const BASE_URL_PRODUCTION = 'https://kyc-api.surepass.io';

    /** The host to call for whichever environment is currently active. */
    public static function baseUrl(): string
    {
        return self::isProduction() ? self::BASE_URL_PRODUCTION : self::BASE_URL_SANDBOX;
    }

    /** Sandbox until a save explicitly switches it — never default a live integration to production. */
    public static function environment(): string
    {
        $value = Setting::get(self::KEY_ENVIRONMENT);

        return $value === self::ENV_PRODUCTION ? self::ENV_PRODUCTION : self::ENV_SANDBOX;
    }

    public static function isProduction(): bool
    {
        return self::environment() === self::ENV_PRODUCTION;
    }

    public static function setEnvironment(string $environment): void
    {
        Setting::put(
            self::KEY_ENVIRONMENT,
            $environment === self::ENV_PRODUCTION ? self::ENV_PRODUCTION : self::ENV_SANDBOX
        );
    }

    public static function sandboxToken(): ?string
    {
        return self::decrypt(self::KEY_SANDBOX_TOKEN);
    }

    public static function productionToken(): ?string
    {
        return self::decrypt(self::KEY_PRODUCTION_TOKEN);
    }

    /** The token for whichever environment is currently active — what every API call should use. */
    public static function activeToken(): ?string
    {
        return self::isProduction() ? self::productionToken() : self::sandboxToken();
    }

    public static function putSandboxToken(string $token): void
    {
        Setting::put(self::KEY_SANDBOX_TOKEN, Crypt::encryptString($token));
    }

    public static function putProductionToken(string $token): void
    {
        Setting::put(self::KEY_PRODUCTION_TOKEN, Crypt::encryptString($token));
    }

    /** Whether the *active* environment has a usable token — the gate a caller checks before calling out. */
    public static function isConfigured(): bool
    {
        return filled(self::activeToken());
    }

    private static function decrypt(string $key): ?string
    {
        $stored = Setting::get($key);

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // A rotated APP_KEY makes every stored secret undecryptable — same failure
            // mode as MailSettings, handled the same way: log it, let the settings
            // screen show "not configured" rather than fail the request that asked.
            Log::warning("Stored Surepass token ({$key}) could not be decrypted — re-enter it in Settings.");

            return null;
        }
    }

    /** Masked for display — enough to confirm which token is saved, not enough to reuse. */
    public static function masked(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        return mb_strlen($token) <= 12
            ? str_repeat('•', mb_strlen($token))
            : mb_substr($token, 0, 6) . str_repeat('•', 10) . mb_substr($token, -6);
    }
}
