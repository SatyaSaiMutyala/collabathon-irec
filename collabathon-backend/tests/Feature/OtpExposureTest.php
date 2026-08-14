<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The OTP is echoed back as `debug_code` because nothing texts it anywhere yet.
 *
 * Which environments do that is the point of these tests: an APP_ENV check alone cannot
 * separate the deployed test server from the real one — both run `production` — so the
 * flag exists to say "this particular production host is a test host".
 */
class OtpExposureTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = '9876543210';

    public function test_the_code_comes_back_off_production_without_any_flag(): void
    {
        // The suite runs as `testing`, so this is the default path.
        $response = $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE]);

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('debug_code'));
    }

    public function test_production_withholds_the_code_by_default(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.otp_expose_code' => false]);

        $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE])
            ->assertOk()
            ->assertJson(['debug_code' => null]);
    }

    /** What makes a deployed test server able to complete the flow. */
    public function test_production_returns_the_code_when_the_flag_is_set(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.otp_expose_code' => true]);

        $response = $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE]);

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('debug_code'));
    }

    /** The echoed code has to be the one that actually verifies, not a decoy. */
    public function test_the_echoed_code_is_the_one_that_verifies(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/send', ['mobile' => self::MOBILE])
            ->json('debug_code');

        $this->postJson('/api/v1/auth/otp/verify', [
            'mobile' => self::MOBILE,
            'code' => $code,
            'device_name' => 'mobile',
        ])->assertOk();
    }
}
