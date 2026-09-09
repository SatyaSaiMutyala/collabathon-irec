<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Server-side twin of the browser compression in `resources/js/app.js`.
 *
 * Every image an admin uploads through the panel is re-encoded in the page before it is
 * sent — 1920px on the longest side, JPEG at quality 82 — which is why a 5 MB phone
 * photo reaches S3 as a few hundred KB. An import has no browser, so without this the
 * Master Data path would be the one route that puts full-resolution originals in the
 * bucket: one registration alone carries 97 MB across 13 files, a 15 MB hero image among
 * them. Those would then be served to every broker opening the listing on mobile data.
 *
 * The constants and the rules are deliberately the same as the JavaScript's, so a
 * listing built by hand and one imported are stored alike. As there, compression is an
 * optimisation and never a gate: anything undecodable, already small, or that would come
 * out bigger is left exactly as it arrived.
 *
 * One knowing difference. The browser applies EXIF orientation while decoding; the `exif`
 * extension is not loaded here, so a photo carrying a rotation flag keeps it rather than
 * being rotated into place. That is the conservative outcome — the pixels are untouched
 * and any viewer honouring EXIF still shows it correctly.
 */
class ImageCompressor
{
    /** Longest side in pixels — ample for a full-bleed hero image. */
    public const MAX_EDGE = 1920;

    /** JPEG quality. Visually clean, roughly a tenth of the source. */
    public const QUALITY = 82;

    /** Below this, re-encoding costs more than it saves. */
    public const SIZE_FLOOR = 320 * 1024;

    /**
     * Decoded-pixel ceiling.
     *
     * GD holds an uncompressed bitmap at about 4 bytes a pixel, so a 30 MP source is
     * ~120 MB of memory before the destination canvas is allocated on top. Past this the
     * file is stored as it came rather than risking the request dying on an allocation
     * that a photo does not justify.
     */
    private const MAX_PIXELS = 30_000_000;

    /**
     * Re-encodes an image file, returning the path to a compressed temporary copy.
     *
     * @return string|null null when the file should be stored unchanged — not an error,
     *                     and callers treat it as "use the original".
     */
    public function compress(string $path): ?string
    {
        if (! is_readable($path) || filesize($path) <= self::SIZE_FLOOR) {
            return null;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        [$width, $height, $type] = $info;

        // GIF may be animated and SVG is vector, so neither is re-encoded — the same two
        // exclusions the browser makes, for the same reasons.
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        if ($width * $height > self::MAX_PIXELS) {
            Log::info('Image too large to re-encode safely — storing the original', [
                'path' => $path,
                'pixels' => $width * $height,
            ]);

            return null;
        }

        $source = $this->read($path, $type);

        if (! $source) {
            return null;
        }

        try {
            $destination = $this->resample($source, $width, $height);
            $target = tempnam(sys_get_temp_dir(), 'img-');

            $written = imagejpeg($destination, $target, self::QUALITY);
            imagedestroy($destination);

            // A small or already-optimised source can re-encode larger. Keep the smaller
            // of the two, exactly as the browser does.
            if (! $written || filesize($target) >= filesize($path)) {
                @unlink($target);

                return null;
            }

            return $target;
        } catch (\Throwable $e) {
            Log::warning('Image compression failed — storing the original', ['error' => $e->getMessage()]);

            return null;
        } finally {
            imagedestroy($source);
        }
    }

    /** @return \GdImage|null */
    private function read(string $path, int $type)
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        return $image ?: null;
    }

    /**
     * Scales to fit MAX_EDGE and flattens onto white.
     *
     * The flatten is not optional: JPEG has no alpha channel, so a PNG logo with a
     * transparent background would otherwise composite onto black. White matches what
     * these images sit on across the panel and the app.
     *
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function resample($source, int $width, int $height)
    {
        $scale = min(1, self::MAX_EDGE / max($width, $height));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $destination = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($destination, 0, 0, imagecolorallocate($destination, 255, 255, 255));
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $destination;
    }
}
