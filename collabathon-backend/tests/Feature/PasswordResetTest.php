<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The admin panel's "forgot password" flow — see Admin\PasswordResetController.
 *
 * The happy path is one test; the rest are the guards, since what this feature is really
 * for is letting exactly one person through and nobody else.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'admin@irec.ae';

    /**
     * Mailjet is configured from the admin panel, not .env, and an unconfigured mailer
     * makes the controller skip sending entirely (see PasswordResetController::deliver()).
     * Every test here is about what happens once the code is on its way, so the
     * credentials are stubbed in rather than each test asserting into a silent void.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::put(MailSettings::KEY_API, 'test-api-key');
        MailSettings::putSecret('test-secret-key');
    }

    private function admin(array $overrides = []): User
    {
        return User::factory()->create([
            'email' => self::EMAIL,
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'password' => 'old-password',
            ...$overrides,
        ]);
    }

    /** The code is never in the response body — it has to come out of the mail. */
    private function codeFromMail(): string
    {
        $sent = Mail::sent(PasswordResetOtpMail::class);
        $this->assertCount(1, $sent, 'Expected exactly one reset code email.');

        return $sent->first()->code;
    }

    /**
     * A code of the right length that is definitely not the issued one.
     *
     * Derived from the real code rather than hardcoded: at CODE_LENGTH digits a literal
     * would collide with a randomly issued code often enough to matter, and the failure
     * would look like a flaky test rather than a correct verification.
     */
    private function wrongCode(string $real): string
    {
        return str_repeat($real[0] === '0' ? '1' : '0', PasswordResetOtp::CODE_LENGTH);
    }

    public function test_an_admin_can_reset_their_password_with_a_code_from_their_inbox(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post('/forgot-password', ['email' => self::EMAIL])
            ->assertRedirect(route('password.verify'));

        $code = $this->codeFromMail();
        $this->assertMatchesRegularExpression('/^\d{' . PasswordResetOtp::CODE_LENGTH . '}$/', $code);

        $this->post('/forgot-password/verify', ['code' => $code])
            ->assertRedirect(route('password.reset'));

        $this->post('/reset-password', [
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-brand-new-password', $admin->fresh()->password));

        // The whole flow is spent — the session can't be replayed for a second reset.
        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }

    /** Each screen is rendered, not just redirected to — Blade errors don't show up in a 302. */
    public function test_every_screen_in_the_flow_renders(): void
    {
        Mail::fake();
        $this->admin();

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Send code')
            ->assertSee(PasswordResetOtp::CODE_LENGTH . '-digit code');

        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $verify = $this->get('/forgot-password/verify')
            ->assertOk()
            ->assertSee('Verify code')
            ->assertSee(PasswordResetOtp::CODE_LENGTH . '-digit code')
            // Masked, so a shoulder-surfer can't read the full address off the screen.
            ->assertSee('ad•••@irec.ae', false)
            ->assertDontSee(self::EMAIL);

        // One box per digit — the screen and the validation rule read the same constant,
        // so this is what would catch them drifting apart.
        $this->assertSame(
            PasswordResetOtp::CODE_LENGTH,
            substr_count($verify->getContent(), 'autocomplete="one-time-code"'),
        );

        $this->post('/forgot-password/verify', ['code' => $this->codeFromMail()]);
        $this->get('/reset-password')->assertOk()->assertSee('Update password');
    }

    /** Mail::fake() never renders the body, so the template is exercised here instead. */
    public function test_the_code_email_renders_with_the_code_in_it(): void
    {
        $mail = new PasswordResetOtpMail('1234', 'Asha');

        $mail->assertSeeInHtml('1234');
        $mail->assertSeeInHtml('Asha');
        $mail->assertHasSubject('Your password reset code: 1234');
    }

    public function test_the_emailed_code_is_never_returned_in_the_response(): void
    {
        Mail::fake();
        $this->admin();

        // `debug_code` is flashed only off-production, and even then it is the session,
        // not the HTTP body — nothing leaks to a caller that isn't the browser in the flow.
        $response = $this->post('/forgot-password', ['email' => self::EMAIL]);

        $response->assertRedirect(route('password.verify'));
        $this->assertStringNotContainsString($this->codeFromMail(), $response->getContent());
    }

    /** The reason the flow is worth having: nothing about the answer varies with the email. */
    public function test_an_unknown_email_looks_exactly_like_a_known_one(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHas('status');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_a_broker_cannot_reset_a_panel_password_here(): void
    {
        Mail::fake();
        $this->admin(['role' => User::ROLE_BROKER]);

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        // Brokers reset from the mobile app; issuing a panel code for one would be a
        // door into /login that their role is not supposed to have.
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_a_paused_admin_cannot_reset_their_way_back_in(): void
    {
        Mail::fake();
        $this->admin(['status' => User::STATUS_PAUSED]);

        $this->post('/forgot-password', ['email' => self::EMAIL]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_a_wrong_code_is_rejected_and_counted(): void
    {
        Mail::fake();
        $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);

        $this->post('/forgot-password/verify', ['code' => $this->wrongCode($this->codeFromMail())])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, PasswordResetOtp::first()->attempts);
        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }

    public function test_a_code_stops_working_after_too_many_wrong_guesses(): void
    {
        Mail::fake();
        $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->codeFromMail();

        for ($i = 0; $i < PasswordResetOtp::MAX_ATTEMPTS; $i++) {
            $this->post('/forgot-password/verify', ['code' => $this->wrongCode($code)]);
        }

        // Even the real code no longer opens it — the lockout is on the challenge, so
        // guessing cannot be resumed just by finally getting it right.
        $this->post('/forgot-password/verify', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->get('/reset-password')->assertRedirect(route('password.request'));
    }

    public function test_an_expired_code_is_rejected(): void
    {
        Mail::fake();
        $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->codeFromMail();

        $this->travel(PasswordResetOtp::TTL_MINUTES + 1)->minutes();

        $this->post('/forgot-password/verify', ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_a_verified_code_cannot_be_used_twice(): void
    {
        Mail::fake();
        $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $code = $this->codeFromMail();

        $this->post('/forgot-password/verify', ['code' => $code])
            ->assertRedirect(route('password.reset'));

        // Consumed on first use, so a replay finds no live challenge.
        $this->post('/forgot-password/verify', ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_a_resend_invalidates_the_previous_code(): void
    {
        Mail::fake();
        $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $first = Mail::sent(PasswordResetOtpMail::class)->first()->code;

        $this->post('/forgot-password/resend')->assertRedirect(route('password.verify'));

        // Only the newest challenge for an address is ever live, so a resend cannot leave
        // two codes a guesser could try against.
        $this->assertSame(1, PasswordResetOtp::whereNull('consumed_at')->count());
        $this->post('/forgot-password/verify', ['code' => $first])
            ->assertSessionHasErrors('code');
    }

    public function test_the_password_step_is_unreachable_without_verifying_a_code(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);

        // Straight to the last step with a live challenge outstanding but unverified.
        $this->post('/reset-password', [
            'password' => 'not-my-account',
            'password_confirmation' => 'not-my-account',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('old-password', $admin->fresh()->password));
    }

    public function test_a_verified_session_expires_before_the_password_is_set(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $this->post('/forgot-password/verify', ['code' => $this->codeFromMail()]);

        $this->travel(16)->minutes();

        $this->post('/reset-password', [
            'password' => 'far-too-late',
            'password_confirmation' => 'far-too-late',
        ])->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('old-password', $admin->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->post('/forgot-password', ['email' => self::EMAIL]);
        $this->post('/forgot-password/verify', ['code' => $this->codeFromMail()]);

        $this->post('/reset-password', [
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $admin->fresh()->password));
    }

    /** A signed-in admin has no business on these screens — `guest` middleware covers them. */
    public function test_a_signed_in_admin_is_bounced_off_the_reset_screens(): void
    {
        $this->actingAs($this->admin())
            ->get('/forgot-password')
            ->assertRedirect();
    }
}
