<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A single OTP challenge for a mobile number. See the migration for why this is keyed
 * on `mobile` rather than `user_id`.
 */
#[Fillable(['mobile', 'code', 'attempts', 'expires_at', 'consumed_at'])]
class OtpCode extends Model
{
    /** Long enough to type from a text message without the app feeling rushed. */
    public const TTL_MINUTES = 5;

    /** A code stops being guessable after this many wrong tries, not just after it expires. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Every challenge gets this same code rather than a random one — same reasoning as
     * {@see EmailOtpCode::FIXED_CODE}, and the same value, so a tester switching the
     * admin's cp_login_method between email and mobile doesn't have to remember two
     * different codes. There is no real SMS provider wired up (see OtpSender) to
     * deliver a random one to anyway.
     */
    private const FIXED_CODE = '8200';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Issues a fresh challenge for a mobile number, invalidating any earlier one — only
     * the most recent code for a number is ever valid, so a resend cannot leave two
     * live codes a guesser could try against.
     */
    public static function issueFor(string $mobile): self
    {
        static::where('mobile', $mobile)->whereNull('consumed_at')->delete();

        $code = self::FIXED_CODE;

        return static::create([
            'mobile' => $mobile,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ])->setRawCode($code);
    }

    /**
     * The plain code, held only in memory on the instance that just issued it — never
     * persisted (`code` on the row is the hash) and never present on a row loaded back
     * out of the database.
     */
    private ?string $rawCode = null;

    private function setRawCode(string $code): self
    {
        $this->rawCode = $code;

        return $this;
    }

    public function rawCode(): ?string
    {
        return $this->rawCode;
    }

    /** The live (unconsumed, unexpired) challenge for a mobile, if any. */
    public static function activeFor(string $mobile): ?self
    {
        return static::where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLocked(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code);
    }

    public function registerFailedAttempt(): void
    {
        $this->increment('attempts');
    }

    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }

    /**
     * A short-lived, single-use proof that this mobile was just verified — carried by
     * the app from `verify` to `register` so the registration endpoint does not have to
     * re-check an OTP (it was already consumed) or fall back to a password. Reuses this
     * row's own hashed `code` as the secret rather than minting a second token: nothing
     * else about a consumed, expired-for-reuse OTP row is guessable from the outside.
     */
    public function verificationToken(): string
    {
        return Str::of((string) $this->id)->append('.')->append(hash('sha256', $this->code))->toString();
    }

    /**
     * Resolves a `verification_token` back to the mobile it was issued for, or null if
     * it does not correspond to a real, recently-consumed challenge — forged, expired
     * past the grace window, or already spent by a completed registration.
     */
    public static function mobileForVerificationToken(?string $token): ?string
    {
        if (! $token || ! str_contains($token, '.')) {
            return null;
        }

        [$id, $hash] = explode('.', $token, 2);

        $otp = static::find($id);

        if (! $otp || ! $otp->consumed_at || hash('sha256', $otp->code) !== $hash) {
            return null;
        }

        // A verified mobile is good for finishing registration within this window, not
        // indefinitely — well past enough to fill in the empanelment form once.
        if ($otp->consumed_at->lt(Carbon::now()->subMinutes(30))) {
            return null;
        }

        return $otp->mobile;
    }
}
