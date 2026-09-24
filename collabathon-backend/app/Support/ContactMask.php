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

    /**
     * A handful of two-label public suffixes common enough to matter here (a developer's
     * own site, a social profile). Not a real public-suffix list — just enough that
     * "satvasolutions.co.in" doesn't get read as label "co", TLD "in", leaving the actual
     * company name sitting in the open as a "subdomain".
     */
    private const TWO_PART_TLDS = [
        'co.in', 'co.uk', 'co.za', 'co.nz', 'co.jp', 'co.id',
        'com.au', 'com.br', 'com.sg',
        'ac.in', 'gov.in', 'net.in', 'org.in',
    ];

    /**
     * Stars the middle of the domain's own name — a company's identity, not the platform
     * or the path — leaving the scheme, the TLD, and a couple of characters on each end
     * visible, e.g. `https://satvasolutions.com/` -> `https://sa************s.com/`.
     *
     * The rest of the URL (path, subdomain, trailing slash) is left alone on purpose:
     * for a company's own website this is usually just the bare domain with nothing else
     * to mask, and masking a path while leaving `satvasolutions.com` in plain sight would
     * still name the company outright — the domain label itself is the identifying part.
     * This is the opposite of what a *social* link needs — see `socialLink()` below,
     * which leaves a well-known platform domain alone and masks the path instead.
     *
     * A value with no real host at all (a bare handle like "@rivermarkhomes", or a plain
     * word with no dot) has nothing to anchor a domain on, so the whole value is masked
     * the same way instead.
     */
    public static function website(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, '@')) {
            // Left to the parser below, a leading "@" reads as URL userinfo
            // ("https://@rivermarkhomes"), which makes "rivermarkhomes" look like the
            // host and leaves it unmasked.
            return '@'.self::maskLabel(substr($value, 1));
        }

        $withScheme = preg_match('#^[a-z][a-z0-9+.\-]*://#i', $value) ? $value : 'https://'.$value;
        $host = parse_url($withScheme, PHP_URL_HOST);

        if (! $host || ! str_contains($host, '.')) {
            return self::maskLabel($value);
        }

        $parts = explode('.', $host);
        $lastTwo = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : null;

        if (count($parts) >= 3 && in_array($lastTwo, self::TWO_PART_TLDS, true)) {
            $tld = $lastTwo;
            $parts = array_slice($parts, 0, -2);
        } else {
            $tld = array_pop($parts);
        }

        $label = array_pop($parts);
        $subdomainPrefix = $parts ? implode('.', $parts).'.' : '';

        if ($label === null) {
            return self::maskLabel($value);
        }

        $maskedHost = $subdomainPrefix.self::maskLabel($label).'.'.$tld;
        $hostPos = stripos($value, $host);

        return substr($value, 0, $hostPos).$maskedHost.substr($value, $hostPos + strlen($host));
    }

    /**
     * Stars the identifying handle in a social profile URL/handle, leaving the platform's
     * own domain visible — unlike `website()`, the domain here (instagram.com,
     * linkedin.com) is generic and shared by everyone, so it names nothing on its own;
     * the account/company slug in the path is what's identifying, e.g.
     * `https://instagram.com/rivermarkhomes` -> `https://instagram.com/rivermarkh****`.
     */
    public static function socialLink(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, '@')) {
            return '@'.self::maskLabel(substr($value, 1));
        }

        $withScheme = preg_match('#^[a-z][a-z0-9+.\-]*://#i', $value) ? $value : 'https://'.$value;
        $host = parse_url($withScheme, PHP_URL_HOST);

        if (! $host || ! str_contains($host, '.')) {
            return self::maskLabel($value);
        }

        $hostPos = stripos($value, $host);
        $visibleLength = $hostPos + strlen($host);
        $rest = substr($value, $visibleLength);

        // Nothing after the host (e.g. just "https://instagram.com") — there is no
        // identifying handle left to hide.
        if (trim($rest, '/') === '') {
            return $value;
        }

        return substr($value, 0, $visibleLength).self::starTail($rest);
    }

    /**
     * Keeps the first two and last one characters of a name and stars everything between
     * — enough left visible that the row reads as a real domain/handle rather than a wall
     * of asterisks, same spirit as `starTail()` but shaped for a short identifying label
     * instead of a long local-part or path.
     */
    private static function maskLabel(string $label): string
    {
        $length = mb_strlen($label);
        if ($length <= 3) {
            return self::starTail($label);
        }

        $head = mb_substr($label, 0, 2);
        $tail = mb_substr($label, -1);

        return $head.str_repeat(self::STAR, $length - 3).$tail;
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
