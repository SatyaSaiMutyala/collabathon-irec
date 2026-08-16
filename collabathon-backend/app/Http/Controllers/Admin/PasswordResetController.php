<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Forgot password" for the admin panel — email, then a 4-digit code from that inbox,
 * then a new password. Three screens rather than the stock signed-link email because the
 * code is the same thing the mobile app already asks partners for, and an admin who has
 * lost their password is usually already reading the inbox on another device.
 *
 * The three steps are chained through the session, not through hidden form fields: the
 * email being reset and the fact that its code was verified are both server-side, so a
 * crafted POST to the last step cannot name an address it never proved control of.
 */
class PasswordResetController extends Controller
{
    /** The email a reset is in progress for, set once step 1 is submitted. */
    private const SESSION_EMAIL = 'password_reset.email';

    /** When the code for that email was verified — absent until step 2 passes. */
    private const SESSION_VERIFIED_AT = 'password_reset.verified_at';

    /**
     * How long a verified code stays good for. Short, because from here on the only thing
     * standing between the session and a new password is the tab staying open.
     */
    private const VERIFIED_WINDOW_MINUTES = 15;

    /**
     * Whether the issued code may be shown on screen instead of only being emailed.
     *
     * Mirrors Api\AuthController::exposesOtpCode(): true off-production regardless, so
     * local work never depends on Mailjet credentials being present, and opt-in via
     * OTP_EXPOSE_CODE on a deployed test server. The real production server sets neither.
     */
    private function exposesCode(): bool
    {
        return ! app()->isProduction() || (bool) config('app.otp_expose_code');
    }

    // ------------------------------------------------------------ step 1: email

    public function showRequest(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Issues a code for the address — if, and only if, it belongs to an account that
     * could actually sign in here.
     *
     * The response is identical either way. Reaching the code screen is not evidence an
     * account exists, so this endpoint can't be used to sift real admin addresses out of
     * a list, which the login form itself is already careful about.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $request->session()->put(self::SESSION_EMAIL, $data['email']);
        $request->session()->forget(self::SESSION_VERIFIED_AT);

        return redirect()->route('password.verify')
            ->with('status', 'If that email belongs to an admin account, a reset code is on its way.')
            ->with($this->issue($data['email']));
    }

    /** Re-sends from the code screen, for a code that expired or never arrived. */
    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        // A fresh code invalidates the old one (see PasswordResetOtp::issueFor), so any
        // half-finished verification on the previous one has to start over.
        $request->session()->forget(self::SESSION_VERIFIED_AT);

        return redirect()->route('password.verify')
            ->with('status', 'A new code has been sent.')
            ->with($this->issue($email));
    }

    /**
     * The shared half of send/resend: create the challenge, mail it, and hand back
     * whatever extra flash data the caller should carry (the code itself, on builds where
     * showing it is allowed).
     *
     * Returns a flash-data array rather than doing the redirect itself so both entry
     * points keep their own status message.
     */
    private function issue(string $email): array
    {
        $user = User::where('email', $email)->whereNull('deleted_at')->first();

        // Only accounts that could sign in at /login get a code. A broker or developer
        // resets from the mobile app, and a paused/rejected admin regaining panel access
        // via their own inbox would defeat the pause.
        if (! $user || ! $user->isAdmin() || ! $user->isActive()) {
            return [];
        }

        $otp = PasswordResetOtp::issueFor($email);
        $this->deliver($user, $otp->rawCode());

        return $this->exposesCode() ? ['debug_code' => $otp->rawCode()] : [];
    }

    /**
     * Same swallow-and-log shape as Api\AuthController::deliverEmailOtp(): the code has
     * already been issued and the caller gets the same response either way, so an
     * unreachable mailer must not surface as a 500 — it should be visible in the logs
     * instead of silently vanishing.
     */
    private function deliver(User $user, string $code): void
    {
        if (! MailSettings::apply()) {
            Log::warning('Password reset code not emailed — no mailer configured.', [
                'email' => $user->email,
            ]);

            return;
        }

        try {
            Mail::to($user->email)->send(new PasswordResetOtpMail($code, $user->name));
        } catch (\Throwable $e) {
            Log::error('Password reset code send failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------ step 2: code

    public function showVerify(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-code', [
            'email' => $email,
            'masked' => $this->mask($email),
            'minutes' => PasswordResetOtp::TTL_MINUTES,
            'length' => PasswordResetOtp::CODE_LENGTH,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        $data = $request->validate([
            'code' => ['required', 'digits:' . PasswordResetOtp::CODE_LENGTH],
        ]);

        $otp = PasswordResetOtp::activeFor($email);

        // Expired, already used, or out of attempts all land here. Same message for all
        // three: the fix is identical (ask for a new code), and spelling out "you have
        // one guess left" only helps someone who is guessing.
        if (! $otp || $otp->isLocked()) {
            throw ValidationException::withMessages([
                'code' => ['That code has expired. Request a new one.'],
            ]);
        }

        if (! $otp->matches($data['code'])) {
            $otp->registerFailedAttempt();

            throw ValidationException::withMessages([
                'code' => ['That code is incorrect.'],
            ]);
        }

        $otp->consume();

        // Regenerated so the id that carries the right to set a new password is not one
        // that existed before the code was proven — session fixation, standard for any
        // step that raises what a session is allowed to do.
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_EMAIL, $email);
        $request->session()->put(self::SESSION_VERIFIED_AT, now()->timestamp);

        return redirect()->route('password.reset');
    }

    // ------------------------------------------------------------ step 3: new password

    public function showReset(Request $request): View|RedirectResponse
    {
        if (! $email = $this->verifiedEmail($request)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'That reset has expired. Start again.']);
        }

        return view('auth.reset-password', ['masked' => $this->mask($email)]);
    }

    public function reset(Request $request): RedirectResponse
    {
        if (! $email = $this->verifiedEmail($request)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'That reset has expired. Start again.']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.min' => 'The password must be at least 8 characters.',
            'password.max' => 'The password cannot be longer than 72 characters.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $user = User::where('email', $email)->whereNull('deleted_at')->first();

        // Re-checked rather than trusted from step 1: the account could have been paused
        // or deleted in the minutes since the code was sent.
        if (! $user || ! $user->isAdmin() || ! $user->isActive()) {
            $this->clear($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'That account can no longer be reset here.']);
        }

        // Plain value in, hashed by the `password` cast on User — same as
        // TeamController::resetPassword(). Hashing here as well would double-hash it.
        $user->update(['password' => $data['password']]);

        // Any "keep me signed in" cookie issued before the reset stops working, and any
        // API token is revoked, so a password changed because someone else may have had
        // it actually locks them out — matching TeamController::resetPassword().
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();

        $this->clear($request);

        return redirect()->route('login')
            ->with('status', 'Password updated. Sign in with your new password.');
    }

    /**
     * The email this session has proven control of, or null — expiry included, so a tab
     * left open overnight cannot still set a password in the morning.
     */
    private function verifiedEmail(Request $request): ?string
    {
        $email = $request->session()->get(self::SESSION_EMAIL);
        $verifiedAt = $request->session()->get(self::SESSION_VERIFIED_AT);

        if (! $email || ! $verifiedAt) {
            return null;
        }

        if (now()->timestamp - (int) $verifiedAt > self::VERIFIED_WINDOW_MINUTES * 60) {
            $this->clear($request);

            return null;
        }

        return $email;
    }

    private function clear(Request $request): void
    {
        $request->session()->forget([self::SESSION_EMAIL, self::SESSION_VERIFIED_AT]);
    }

    /** `admin@irec.ae` -> `ad•••@irec.ae` — enough to confirm which inbox to open. */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $head = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));

        return $head . str_repeat('•', 3) . ($domain ? '@' . $domain : '');
    }
}
