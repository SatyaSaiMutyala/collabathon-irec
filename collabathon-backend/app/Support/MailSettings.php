<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outbound email, configured from the admin panel rather than from .env.
 *
 * Mailjet is reached over plain SMTP (in-v3.mailjet.com), so no vendor package is needed:
 * the API key is the SMTP username and the secret key is the password. That keeps the
 * transport something Laravel already knows how to speak and something a sysadmin can
 * debug with any SMTP tool.
 *
 * The secret is encrypted at rest with the app key. It is a credential that can send mail
 * as this organisation, and `settings` is a table that gets dumped, copied to staging and
 * pasted into support threads like any other.
 */
class MailSettings
{
    public const KEY_API = 'mailjet_api_key';
    public const KEY_SECRET = 'mailjet_secret_key';
    public const KEY_FROM_ADDRESS = 'mail_from_address';
    public const KEY_FROM_NAME = 'mail_from_name';

    private const HOST = 'in-v3.mailjet.com';
    private const PORT = 587;

    /** Settings first, .env second — so a deployment can ship keys without the UI. */
    public static function apiKey(): ?string
    {
        return Setting::get(self::KEY_API) ?: env('MAILJET_API_KEY') ?: null;
    }

    public static function secretKey(): ?string
    {
        $stored = Setting::get(self::KEY_SECRET);

        if (! $stored) {
            return env('MAILJET_SECRET_KEY') ?: null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // A rotated APP_KEY makes every stored secret undecryptable. Failing loudly
            // here would break the approvals page; the settings screen reports it instead.
            Log::warning('Stored Mailjet secret could not be decrypted — re-enter it in Settings.');

            return null;
        }
    }

    public static function fromAddress(): string
    {
        return Setting::get(self::KEY_FROM_ADDRESS)
            ?: config('mail.from.address')
            ?: 'no-reply@example.com';
    }

    public static function fromName(): string
    {
        return Setting::get(self::KEY_FROM_NAME)
            ?: config('mail.from.name')
            ?: config('app.name');
    }

    public static function putSecret(string $secret): void
    {
        Setting::put(self::KEY_SECRET, Crypt::encryptString($secret));
    }

    /** Both halves are needed; one alone cannot authenticate. */
    public static function isConfigured(): bool
    {
        return filled(self::apiKey()) && filled(self::secretKey());
    }

    /**
     * Points the default mailer at Mailjet for the rest of this request.
     *
     * Done at call time rather than in a service provider so a key saved in the admin
     * panel takes effect on the very next send, with no cache clear and no redeploy.
     * Returns false when nothing is configured, which is the caller's cue to skip
     * sending rather than to throw.
     */
    public static function apply(): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        Config::set('mail.default', 'mailjet');
        Config::set('mail.mailers.mailjet', [
            'transport' => 'smtp',
            'host' => self::HOST,
            'port' => self::PORT,
            'encryption' => 'tls',
            'username' => self::apiKey(),
            'password' => self::secretKey(),
            'timeout' => 15,
        ]);
        Config::set('mail.from.address', self::fromAddress());
        Config::set('mail.from.name', self::fromName());

        // The mailer is resolved once per request and caches its transport, so a manager
        // built before this call would still hold the old one.
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');

        return true;
    }

    /** Masked for display — enough to confirm which key is in use, not enough to reuse. */
    public static function maskedApiKey(): ?string
    {
        $key = self::apiKey();

        if (! $key) {
            return null;
        }

        return mb_strlen($key) <= 8
            ? str_repeat('•', mb_strlen($key))
            : mb_substr($key, 0, 4) . str_repeat('•', 8) . mb_substr($key, -4);
    }
}
