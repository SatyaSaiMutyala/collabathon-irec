<?php

namespace App\Support;

/**
 * Turns a contact channel into a placeholder that is still recognisably itself.
 *
 * This is what the API sends while a broker's request is pending — the real value never
 * leaves the server, so there is nothing in the payload for a client to un-hide. Only
 * the last few characters are starred: enough that the channel cannot be used, little
 * enough that the developer sees a real person rather than a wall of asterisks.
 *
 * Note the deliberate trade-off. Four hidden digits make a number undialable, but the
 * visible remainder plus the broker's name is a strong hint — this masking is a courtesy
 * gate, not a defence against someone determined to guess. The real protection is that
 * the unmasked value is never serialised until the developer accepts.
 */
final class ContactMask
{
    private const STAR = '*';

    /** How many characters to hide. Four is the most that still leaves a value readable. */
    private const HIDDEN = 4;

    /**
     * Stars the last four digits and leaves everything else — country code, separators,
     * brackets — intact, so the number keeps its shape and region.
     */
    public static function phone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digitPositions = [];
        foreach (str_split($value) as $i => $char) {
            if (ctype_digit($char)) {
                $digitPositions[] = $i;
            }
        }

        // A value with nothing but a handful of digits gets starred outright rather than
        // handed over with one digit covered.
        $toHide = count($digitPositions) > self::HIDDEN
            ? array_slice($digitPositions, -self::HIDDEN)
            : $digitPositions;

        $out = $value;
        foreach ($toHide as $i) {
            $out[$i] = self::STAR;
        }

        return $out;
    }

    /**
     * Stars the tail of the local part and keeps the domain, which is how a masked address
     * is conventionally shown and keeps the company recognisable.
     */
    public static function email(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $at = strrpos($value, '@');
        if ($at === false) {
            return self::starTail($value);
        }

        return self::starTail(substr($value, 0, $at)) . substr($value, $at);
    }

    /** Stars the trailing characters, always leaving at least one visible unless it cannot. */
    private static function starTail(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 1) {
            return str_repeat(self::STAR, max($length, 1));
        }

        $hide = min(self::HIDDEN, $length - 1);

        return mb_substr($value, 0, $length - $hide) . str_repeat(self::STAR, $hide);
    }
}
