<?php

namespace App\Support;

/**
 * Which viewer an uploaded file needs — the one thing the preview overlay cannot work
 * out for itself.
 *
 * Decided here rather than in the browser because the URL the overlay receives is often
 * a signed S3 link: the extension is buried behind a query string, and a private object
 * served with a generic content type gives the client nothing to go on. The stored path
 * is always plain and always right, so the kind is settled server-side and travels with
 * the link.
 *
 * Four kinds, because that is how many the overlay actually renders differently. Anything
 * a browser will not display inline — a spreadsheet, a zip, a Word file — is 'file', and
 * the overlay offers to open or download it instead of pretending to show it.
 */
final class FilePreview
{
    /** Formats every current browser renders in an <img>. */
    private const IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg', 'heic', 'heif'];

    /** Formats a <video> element can play without a library. */
    private const VIDEO = ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v'];

    public static function kind(?string $path): string
    {
        // parse_url first: a signed URL ends in "...brochure.pdf?X-Amz-Signature=…", and
        // pathinfo() on that returns "pdf?X-Amz-Signature=…" as the extension. A plain
        // storage path passes through unchanged.
        $withoutQuery = parse_url((string) $path, PHP_URL_PATH);

        $extension = strtolower(pathinfo($withoutQuery ?? (string) $path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, self::IMAGE, true) => 'image',
            $extension === 'pdf' => 'pdf',
            in_array($extension, self::VIDEO, true) => 'video',
            default => 'file',
        };
    }

    /** Whether the overlay can show this inline at all, as opposed to offering it as a download. */
    public static function isViewable(?string $path): bool
    {
        return self::kind($path) !== 'file';
    }
}
