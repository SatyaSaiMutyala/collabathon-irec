<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * A single "forgot password" challenge for an admin-panel email address.
 *
 * Same machinery as {@see OtpCode}, and specifically *not* {@see EmailOtpCode}: that one
 * issues a fixed code on purpose so the mobile sign-in never strands an app-store
 * reviewer. A code that resets a panel password has to be random — see the migration.
 */
#[Fillable(['email', 'code', 'attempts', 'expires_at', 'consumed_at'])]
class PasswordResetOtp extends Model
{
    /** Longer than the sign-in codes: a reset means leaving the browser for an inbox. */
    public const TTL_MINUTES = 10;

    /** A code stops being guessable after this many wrong tries, not just after it expires. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Digits in an issued code — matching the 4 the mobile sign-in already asks for, so
     * "the code from your email" means the same shape everywhere.
     *
     * Declared here rather than written into the validation rule and the box count
     * separately: those three have to agree, and a mismatch would be a flow that issues
     * codes nobody can enter. MAX_ATTEMPTS is what keeps the smaller space safe — 5 tries
     * against 10,000 values, and the challenge dies.
     */
    public const CODE_LENGTH = 4;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Issues a fresh challenge for an email, invalidating any earlier one — only the most
     * recent code for an address is ever valid, so a resend cannot leave two live codes a
     * guesser could try against.
     */
    public static function issueFor(string $email): self
    {
        static::where('email', $email)->whereNull('consumed_at')->delete();

        // Zero-padded rather than range-limited, so "0042" is as likely as any other code
        // and the full 10^CODE_LENGTH space is actually used.
        $code = str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );

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
