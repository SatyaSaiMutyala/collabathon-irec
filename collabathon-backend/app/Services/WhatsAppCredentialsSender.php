<?php

namespace App\Services;

use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers login credentials (name, email, password) over WhatsApp via MSG91 — same
 * account and integrated number as {@see OtpSender}, but a separate template.
 *
 * Meta's Authentication category (used for OTP) is locked to a bare code with a copy
 * button; it cannot carry an email address or password. This needs a Utility-category
 * template of its own, approved independently on the MSG91/WhatsApp Business dashboard
 * — see WhatsAppSettings::isCredentialsConfigured().
 *
 * Unlike OtpSender, an unconfigured template here is not a failure: email is always the
 * primary channel for credentials, so this is skipped quietly until the template exists.
 */
class WhatsAppCredentialsSender
{
    /**
     * @return bool Whether the message was actually handed to MSG91. False when the
     *              credentials template isn't configured yet, or the API call failed.
     */
    public function send(string $mobile, string $name, string $email, string $password): bool
    {
        if (! WhatsAppSettings::isCredentialsConfigured()) {
            return false;
        }

        $namespace = WhatsAppSettings::credentialsTemplateNamespace();

        $template = array_filter([
            'name' => WhatsAppSettings::credentialsTemplateName(),
            'language' => [
                'code' => WhatsAppSettings::credentialsTemplateLanguage(),
                'policy' => 'deterministic',
            ],
            'namespace' => $namespace,
            'to_and_components' => [
                [
                    'to' => [self::toE164($mobile)],
                    'components' => [
                        // Must match the approved template's variable order exactly —
                        // {{1}} name, {{2}} email, {{3}} password. If the real template
                        // ends up with a different variable count or order, this is the
                        // one place to change.
                        'body_1' => ['type' => 'text', 'value' => $name],
                        'body_2' => ['type' => 'text', 'value' => $email],
                        'body_3' => ['type' => 'text', 'value' => $password],
                    ],
                ],
            ],
        ]);

        try {
            $response = Http::withHeaders(['authkey' => WhatsAppSettings::activeToken()])
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->timeout(10)
                ->post(WhatsAppSettings::API_URL, [
                    'integrated_number' => WhatsAppSettings::integratedNumber(),
                    'content_type' => 'template',
                    'payload' => [
                        'messaging_product' => 'whatsapp',
                        'type' => 'template',
                        'template' => $template,
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('MSG91 WhatsApp credentials send failed', [
                'mobile' => $mobile,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('MSG91 WhatsApp credentials send threw', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Same E.164-ish shape OtpSender needs — see its own note on why it stays local. */
    private static function toE164(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);

        return str_starts_with($digits, '91') ? $digits : '91' . $digits;
    }
}
