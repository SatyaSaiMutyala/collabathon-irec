<?php

namespace App\Services;

use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers an OTP to a mobile number over WhatsApp (MSG91).
 *
 * WhatsApp is the only channel — there is deliberately no log fallback and the code is
 * never echoed back through the API. An unconfigured integration is therefore a failed
 * send, not a quietly successful one, so a missing MSG91 key surfaces immediately
 * instead of looking like a delivery problem on the phone's end.
 *
 * `AuthController` depends on this class, not on an HTTP call directly, so swapping
 * providers later is "replace what's inside `send()`", not a hunt through the
 * controller — same reasoning as the class docblock already had for the log-only
 * version.
 */
class OtpSender
{
    /**
     * @return bool Whether the code was actually handed to a delivery channel. `false`
     *              only when WhatsApp is configured but the API call itself failed —
     *              never for "not configured yet", which is the log fallback, not a
     *              failure.
     */
    public function send(string $mobile, string $code): bool
    {
        if (! WhatsAppSettings::isConfigured()) {
            // Deliberately not logging the code. There is no fallback delivery channel
            // any more: an unconfigured integration is a failed send, so the caller
            // surfaces an error rather than the app silently "succeeding" with a code
            // nobody received.
            Log::error('OTP not sent — WhatsApp (MSG91) is not configured.', ['mobile' => $mobile]);

            return false;
        }

        return $this->sendViaWhatsApp($mobile, $code);
    }

    /**
     * One outbound template message via MSG91's WhatsApp API — the same authentication
     * template category Meta requires for any OTP sent over WhatsApp (a fixed layout
     * with a copy-code button; that's a WhatsApp Business platform rule, not an MSG91
     * one). The template itself — name, namespace, language — is created once in the
     * MSG91 dashboard and only referenced here by name; this method never builds or
     * changes template content, only fills in the one variable it takes: the code.
     */
    private function sendViaWhatsApp(string $mobile, string $code): bool
    {
        $namespace = WhatsAppSettings::templateNamespace();

        $template = array_filter([
            'name' => WhatsAppSettings::templateName(),
            'language' => [
                'code' => WhatsAppSettings::templateLanguage(),
                'policy' => 'deterministic',
            ],
            'namespace' => $namespace,
            'to_and_components' => [
                [
                    'to' => [self::toE164($mobile)],
                    'components' => [
                        // MSG91's own placeholder name for a template's first body
                        // variable — an authentication template has exactly one
                        // (the code), so there is only ever a `body_1` to fill.
                        'body_1' => ['type' => 'text', 'value' => $code],
                        // Meta's OTP-authentication template layout attaches a "Copy
                        // code" button whose payload must match the body variable —
                        // harmless to include even for a template built without one.
                        'button_1' => ['subtype' => 'url', 'type' => 'text', 'value' => $code],
                    ],
                ],
            ],
        ]);

        try {
            $response = Http::withHeaders(['authkey' => WhatsAppSettings::activeToken()])
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

            Log::error('MSG91 WhatsApp OTP send failed', [
                'mobile' => $mobile,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('MSG91 WhatsApp OTP send threw', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * MSG91 expects a number with country code and no leading `+` (e.g. `9198XXXXXXXX`).
     * Every mobile number reaching this class is already the plain 10-digit form
     * `OtpCode`/`AuthController` validate — this is the one place that needs the E.164-ish
     * shape, so it stays local rather than changing what's stored everywhere else.
     */
    private static function toE164(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);

        return str_starts_with($digits, '91') ? $digits : '91' . $digits;
    }
}
