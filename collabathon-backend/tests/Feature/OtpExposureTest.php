<?php

namespace Tests\Feature;

use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp (MSG91) is the only OTP delivery channel.
 *
 * The code is never echoed back in the API response and there is no log fallback, so
 * these tests pin the two things that follow from that: a send that could not happen is
 * reported as a failure, and a send that did happen still tells the caller nothing about
 * the code itself.
 */
class OtpExposureTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = '9876543210';

    /** Puts a usable sandbox configuration in place so `isConfigured()` passes. */
    private function configureWhatsApp(): void
    {
        WhatsAppSettings::putSandboxToken('test-auth-key');
        WhatsAppSettings::putConfig('919876543210', 'collabathon_otp', null, 'en');
        WhatsAppSettings::setEnvironment(WhatsAppSettings::ENV_SANDBOX);
    }

    public function test_an_unconfigured_integration_reports_the_send_as_failed(): void
    {
        Http::fake();

        $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE])
            ->assertStatus(502);

        // Nothing was attempted — the guard fires before any HTTP call.
        Http::assertNothingSent();
    }

    public function test_a_rejected_send_reports_the_send_as_failed(): void
    {
        $this->configureWhatsApp();
        Http::fake([WhatsAppSettings::API_URL . '*' => Http::response(['message' => 'nope'], 400)]);

        $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE])
            ->assertStatus(502);
    }

    public function test_the_code_is_never_returned_in_the_response(): void
    {
        $this->configureWhatsApp();
        Http::fake([WhatsAppSettings::API_URL . '*' => Http::response(['type' => 'success'], 200)]);

        $response = $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE])
            ->assertOk();

        $this->assertNull($response->json('debug_code'));
        $this->assertArrayNotHasKey('debug_code', $response->json());
    }

    /** The code MSG91 was handed is the one that actually verifies. */
    public function test_the_code_sent_to_whatsapp_is_the_one_that_verifies(): void
    {
        $this->configureWhatsApp();
        Http::fake([WhatsAppSettings::API_URL . '*' => Http::response(['type' => 'success'], 200)]);

        $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE])->assertOk();

        // The only place the plaintext code exists now is the outbound payload.
        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            $code = data_get($request->data(), 'payload.template.to_and_components.0.components.body_1.value');

            return $code !== null;
        });

        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);

        $this->postJson('/api/v1/auth/otp/verify', [
            'mobile' => self::MOBILE,
            'code' => $code,
            'device_name' => 'mobile',
        ])->assertOk();
    }
}
