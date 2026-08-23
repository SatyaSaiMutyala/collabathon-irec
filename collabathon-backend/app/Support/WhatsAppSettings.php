<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp OTP delivery (MSG91), configured from the admin panel — same reasoning as
 * {@see SurepassSettings}: an auth key that can send messages on this organisation's
 * behalf belongs encrypted in `settings`, not in `.env`.
 *
 * Sandbox and production are two independent auth keys, not one key pointed at two
 * hosts — MSG91's API endpoint never changes between them, only the account (and
 * therefore the key, the integrated WhatsApp number, and the approved template) does.
 * Build and test against a sandbox/test account first; flip `KEY_ENVIRONMENT` to
 * production only once a real OTP has actually arrived on a phone.
 *
 * `OtpSender` is the only caller — this class just stores and hands back whatever it
 * asks for, the same division of responsibility SurepassSettings already has with
 * KycController.
 */
class WhatsAppSettings
{
    public const KEY_SANDBOX_TOKEN = 'whatsapp_sandbox_token';
    public const KEY_PRODUCTION_TOKEN = 'whatsapp_production_token';
    public const KEY_ENVIRONMENT = 'whatsapp_environment';

    // Not secret — the business's own WhatsApp number and the template it sends
    // through, both visible on the MSG91 dashboard already. Stored plain, same as
    // `cp_login_method`, rather than through the encrypted token pair above.
    public const KEY_INTEGRATED_NUMBER = 'whatsapp_integrated_number';
    public const KEY_TEMPLATE_NAME = 'whatsapp_template_name';
    public const KEY_TEMPLATE_NAMESPACE = 'whatsapp_template_namespace';
    public const KEY_TEMPLATE_LANGUAGE = 'whatsapp_template_language';

    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    /** MSG91's own send endpoint — the same URL for every account; only the key differs. */
    public const API_URL = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

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

    /** The auth key for whichever environment is currently active — what every API call should use. */
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

    public static function integratedNumber(): ?string
    {
        return Setting::get(self::KEY_INTEGRATED_NUMBER);
    }

    public static function templateName(): ?string
    {
        return Setting::get(self::KEY_TEMPLATE_NAME);
    }

    public static function templateNamespace(): ?string
    {
        return Setting::get(self::KEY_TEMPLATE_NAMESPACE);
    }

    /** 'en' unless a save ever picks something else — MSG91 defaults every template to English too. */
    public static function templateLanguage(): string
    {
        return Setting::get(self::KEY_TEMPLATE_LANGUAGE) ?: 'en';
    }

    public static function putConfig(string $integratedNumber, string $templateName, ?string $namespace, string $language): void
    {
        Setting::put(self::KEY_INTEGRATED_NUMBER, $integratedNumber);
        Setting::put(self::KEY_TEMPLATE_NAME, $templateName);
        Setting::put(self::KEY_TEMPLATE_NAMESPACE, $namespace);
        Setting::put(self::KEY_TEMPLATE_LANGUAGE, $language ?: 'en');
    }

    /**
     * Whether there is enough here to actually attempt a send — the active token plus
     * every piece of the template MSG91 needs to address. Namespace is deliberately
     * excluded: some MSG91 accounts don't require it on this endpoint, so a template
     * without one must not be treated as "unconfigured".
     */
    public static function isConfigured(): bool
    {
        return filled(self::activeToken())
            && filled(self::integratedNumber())
            && filled(self::templateName());
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
            // mode as MailSettings/SurepassSettings, handled the same way: log it, let
            // the settings screen show "not configured" rather than fail the request
            // that asked.
            Log::warning("Stored WhatsApp auth key ({$key}) could not be decrypted — re-enter it in Settings.");

            return null;
        }
    }

    /** Masked for display — enough to confirm which key is saved, not enough to reuse. */
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
