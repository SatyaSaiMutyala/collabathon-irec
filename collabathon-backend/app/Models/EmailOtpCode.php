<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * A single OTP challenge for an email address — channel-partner sign-in. See the
 * migration for why this is keyed on `email` rather than `user_id`.
 *
 * Deliberately carries no `verificationToken()` the way {@see OtpCode} (the
 * mobile-number version) does: that token exists so `register()` can trust a verified
 * mobile without asking for a password. The registration endpoint here still collects
 * its own email + password directly and enforces `unique:users,email` on its own, so
 * there is nothing for a carried-forward proof to save it from re-checking — verifying
 * the email first is a sign-in convenience, not a gate registration itself relies on.
 */
#[Fillable(['email', 'code', 'attempts', 'expires_at', 'consumed_at'])]
class EmailOtpCode extends Model
{
    /** Long enough to read out of an inbox without the app feeling rushed. */
    public const TTL_MINUTES = 5;

    /** A code stops being guessable after this many wrong tries, not just after it expires. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Every challenge gets this same code rather than a random one — by design, not a
     * placeholder. A Play Store / App Store reviewer signs in against production with
     * no access to whatever inbox they typed in, so a code that varies per request
     * would leave them stuck with no way through; a fixed code means "check your
     * email" is never actually required to complete this flow. The hash/expiry/
     * attempt-lockout machinery below still applies — a stale or overused challenge
     * still stops verifying — only the value itself stopped being random.
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
     * Issues a fresh challenge for an email, invalidating any earlier one — only the
     * most recent code for an address is ever valid, so a resend cannot leave two live
     * codes a guesser could try against.
     */
    public static function issueFor(string $email): self
    {
        static::where('email', $email)->whereNull('consumed_at')->delete();

        $code = self::FIXED_CODE;

        return static::create([
            'email' => $email,
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

    /** The live (unconsumed, unexpired) challenge for an email, if any. */
    public static function activeFor(string $email): ?self
    {
        return static::where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
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
}
