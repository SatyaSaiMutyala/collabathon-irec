<?php

namespace App\Services\MasterData;

use App\Support\FileStorage;
use App\Support\ImageCompressor;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Copies a file the vendor exposes by URL onto our own storage, compressed the same way
 * an admin's own upload would be.
 *
 * Two things shape this class. The first is that everything is best-effort: an import
 * brings across an account and a listing, and a photo that 404s, times out or comes back
 * as an HTML error page must not cost the admin the whole conversion. Every failure
 * returns null and is logged with its URL, so a missing image can be traced afterwards
 * rather than silently wondered about.
 *
 * The second is size. These are full-resolution originals — one registration carries
 * 97 MB across 13 files — so nothing is held in memory: the download is streamed
 * straight to a temporary file, re-encoded through {@see ImageCompressor}, and streamed
 * again into the destination disk. Holding a 15 MB body as a PHP string and then handing
 * that to S3 is what turns a large listing into an out-of-memory error.
 */
class RemoteAsset
{
    /**
     * Per-file ceiling. Generous because these files are genuinely large and the caller
     * already bounds the total time across all of them.
     */
    private const TIMEOUT_SECONDS = 30;

    /** Anything larger is not a listing photo or brochure — refuse it rather than fill the disk. */
    private const MAX_BYTES = 40 * 1024 * 1024;

    public function __construct(private readonly ImageCompressor $compressor) {}

    /**
     * Downloads one URL into `$folder` and returns the stored path.
     *
     * @param  string  $folder  Destination folder, e.g. "properties/12" — the disk is
     *                          chosen from it by {@see FileStorage::diskForFolder()}, so
     *                          secure folders stay off the public disk automatically.
     */
    public function fetch(?string $url, string $folder): ?string
    {
        if (! $this->isFetchable($url)) {
            return null;
        }

        $download = tempnam(sys_get_temp_dir(), 'md-');
        $compressed = null;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->sink($download)->get($url);

            if (! $response->successful()) {
                Log::warning('Master Data asset fetch returned an error status', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            // tempnam() created this path empty and the download filled it through a
            // separate handle, so PHP's stat cache still remembers zero bytes. Without
            // clearing it every asset reads as empty and is skipped.
            clearstatcache(true, $download);

            $size = filesize($download) ?: 0;

            if ($size === 0 || $size > self::MAX_BYTES) {
                Log::warning('Master Data asset skipped — empty or oversized', ['url' => $url, 'bytes' => $size]);

                return null;
            }

            // Re-encoded to the same 1920px/quality-82 shape the browser applies to an
            // admin's own upload, so an imported listing does not become the one place
            // full-resolution originals reach the bucket. Null means the file is better
            // left alone — a PDF, an already-small image, one that grew.
            $compressed = $this->compressor->compress($download);
            $stored = $compressed ?? $download;

            $name = Str::random(24) . '.' . ($compressed ? 'jpg' : $this->extension($url, $response->header('Content-Type')));

            // putFileAs streams from the temporary file rather than reading it into a
            // string first — the whole reason the download went to disk.
            return FileStorage::diskForFolder($folder)->putFileAs($folder, new File($stored), $name) ?: null;
        } catch (\Throwable $e) {
            Log::warning('Master Data asset fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($download);

            if ($compressed) {
                @unlink($compressed);
            }
        }
    }

    /**
     * Only absolute http(s) URLs are followed. The vendor occasionally sends an empty
     * string or a bare filename for an asset that was never uploaded, and neither is
     * worth a request — nor is any other scheme worth reaching for from the server.
     */
    private function isFetchable(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * The URL's own extension is trusted first because it is what the vendor's CDN
     * serves the file as. Content-Type is the fallback for the query-string URLs that
     * carry no extension at all, and 'bin' the last resort — a stored file with an odd
     * extension is still recoverable, a failed import is not.
     */
    private function extension(string $url, ?string $contentType): string
    {
        $fromUrl = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (preg_match('/^[a-z0-9]{2,5}$/', $fromUrl)) {
            return $fromUrl;
        }

        return match (Str::before(strtolower((string) $contentType), ';')) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
