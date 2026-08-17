<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use Zxing\QrReader;

/**
 * Verifies a channel partner's Aadhaar three ways, matching the three documents a
 * broker might actually have on hand:
 *
 *   - `verifyFromXml()` — the offline XML downloaded from the UIDAI website. A plain
 *     file upload, no decode step to fail.
 *   - `verifyFromEaadhaar()` — the eAadhaar PDF, also downloaded from UIDAI. Same
 *     idea, a different Surepass endpoint because UIDAI's PDF is typically
 *     password-protected (first letters of name + year of birth), which
 *     `verifyFromEaadhaar()` passes along if the caller has them.
 *   - `verifyFromImage()` — reads the QR code off a photo of the physical card
 *     instead of either downloaded file. Convenient, but only as reliable as the
 *     photo — see its own docblock for why a real Aadhaar "Secure QR" needs more
 *     resolution than a casual card photo reliably delivers.
 *
 * All three call Surepass endpoints this app was always going to need — one class,
 * not three, since they share the client and the response shape.
 */
class AadhaarVerificationService
{
    public function __construct(private SurepassClient $client)
    {
    }

    /**
     * @return array{status: 'verified'|'rejected'|'unavailable', data?: array, message?: string}
     */
    public function verifyFromXml(string $xmlPath, ?string $shareCode = null): array
    {
        try {
            $response = $this->client->postMultipart(
                '/api/v1/aadhaar/upload/xml',
                $shareCode ? ['share_code' => $shareCode] : [],
                [['name' => 'file', 'contents' => file_get_contents($xmlPath), 'filename' => 'aadhaar.xml']],
            );
        } catch (Throwable $e) {
            Log::error('Surepass Aadhaar XML verification unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not reach the verification service. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::info('Aadhaar XML rejected by Surepass', ['message' => $response->json('message')]);

            return [
                'status' => 'rejected',
                // Surepass's own wording covers the two real causes here: a share-code
                // mismatch, or a file that isn't a genuine UIDAI-signed offline XML.
                'message' => $response->json('message') ?? 'That file did not verify as a valid Aadhaar XML.',
            ];
        }

        return [
            'status' => 'verified',
            'data' => $response->json('data'),
        ];
    }

    /**
     * `yob`/`fullName` default to an empty string rather than being omitted —
     * Surepass's own request schema marks both required alongside `file` and
     * `base64`, even though its prose describes `yob` as optional. If the caller
     * doesn't actually have them (the broker didn't type a name yet, or skipped
     * year of birth) and the PDF turns out to be password-protected, Surepass
     * fails the same way an unavailable password always would: a `rejected`
     * outcome, not a crash, exactly like the "wrong share code" case in
     * verifyFromXml().
     *
     * @return array{status: 'verified'|'rejected'|'unavailable', data?: array, message?: string}
     */
    public function verifyFromEaadhaar(string $pdfPath, ?string $yob = null, ?string $fullName = null): array
    {
        $contents = file_get_contents($pdfPath);

        $fields = [
            'yob' => $yob ?? '',
            'full_name' => $fullName ?? '',
            // Surepass's schema lists this as a required field alongside `file` —
            // a base64 copy of the same PDF, not a separate document.
            'base64' => base64_encode($contents),
        ];

        try {
            $response = $this->client->postMultipart(
                '/api/v1/aadhaar/upload/eaadhaar',
                $fields,
                [['name' => 'file', 'contents' => $contents, 'filename' => 'aadhaar.pdf']],
            );
        } catch (Throwable $e) {
            Log::error('Surepass Aadhaar eAadhaar verification unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not reach the verification service. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::info('Aadhaar eAadhaar rejected by Surepass', ['message' => $response->json('message')]);

            return [
                'status' => 'rejected',
                'message' => $response->json('message') ?? 'That PDF did not verify as a valid eAadhaar.',
            ];
        }

        return [
            'status' => 'verified',
            'data' => $response->json('data'),
        ];
    }

    /**
     * @return array{status: 'verified'|'qr_not_found'|'rejected'|'unavailable', data?: array, message?: string}
     */
    public function verifyFromImage(string $imagePath): array
    {
        $qrText = $this->decodeQr($imagePath);

        if ($qrText === null) {
            return [
                'status' => 'qr_not_found',
                'message' => 'Could not read a QR code in that photo. Try a clearer, well-lit photo of the card.',
            ];
        }

        try {
            $response = $this->client->postJson('/api/v1/aadhaar/upload/qr', ['qr_text' => $qrText]);
        } catch (Throwable $e) {
            Log::error('Surepass Aadhaar QR verification unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not reach the verification service. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return [
                'status' => 'rejected',
                'message' => $response->json('message') ?? 'That QR code did not verify as a valid Aadhaar card.',
            ];
        }

        return [
            'status' => 'verified',
            'data' => $response->json('data'),
        ];
    }

    /**
     * A full-resolution phone camera photo (commonly 3000-4000px on a side) decodes to
     * a raw GD bitmap several times larger than the JPEG file itself — a 12MP photo is
     * ~48MB just as pixels, before the QR library's own internal copies. That alone was
     * enough to exceed PHP's default 128MB memory_limit and crash the whole request with
     * an uncatchable fatal error, not something a try/catch here could ever recover from
     * — decodeQr() downscales *before* handing anything to QrReader, so the crash never
     * has a chance to happen in the first place.
     *
     * This is a backstop, not the main size control — the picker on the mobile side
     * already caps a photo at 1600px on its longest side before it's ever uploaded
     * (see AttachField.js), and this value has to match that exactly, not just be
     * "close enough":
     *
     *   - Any lower (tried 1200px) and a normal, already-capped 1600px photo gets
     *     resized DOWN again here. A real Aadhaar "Secure QR" is a dense, high-version
     *     code (~2-3KB of demographic data plus a digital signature packed in), and
     *     when it only occupies a modest corner of a whole-card photo rather than a
     *     tight close-up, that second unnecessary shrink destroys enough fine module
     *     detail to make an otherwise-decodable code fail. Reproduced directly: a
     *     synthetic QR at that density decoded fine at the client's native 1600px,
     *     failed after this method resized it down to 1200px or 1400px.
     *   - Any higher (tried 2400px) and the fatal-memory crash this whole method
     *     exists to prevent comes back for a genuinely oversized source (an older
     *     client build, or a device picker that ignored the maxWidth/maxHeight cap):
     *     the *original* decode before any resizing happens is what actually costs the
     *     memory — imagecreatefromjpeg() always decodes the source at its real size
     *     regardless of the target — so a bigger resize target just adds a bigger
     *     second buffer on top of that same fixed cost. Reproduced directly too: a
     *     4032x3024 source crashed PHP's default 128MB limit at a 2400px target,
     *     stayed comfortably under it at 1600px.
     *
     * 1600 is the one value that is both "large enough to never touch a normal photo"
     * and "small enough to never touch the memory ceiling" at once — it isn't a
     * compromise between those two failure modes, it's the exact width the boundary
     * sits at for this pipeline.
     */
    private const MAX_DECODE_DIMENSION = 1600;

    /**
     * Null, not an exception — an unreadable QR is an expected, common outcome for a
     * card photo (glare, blur, a corner cut off), not a system failure.
     *
     * Logs every outcome, not just exceptions — the "no QR found" branch below used to
     * log nothing at all, which meant the single most common failure mode (an
     * unreadable photo, as opposed to a crash or a network error) left zero trace to
     * debug a real report against. Source dimensions and whether a resize actually ran
     * are the two facts that distinguish "the photo itself wasn't decodable" from "the
     * resize step degraded it" after the fact, from the log alone.
     */
    private function decodeQr(string $imagePath): ?string
    {
        $sourceInfo = @getimagesize($imagePath);
        $decodePath = $this->downscaleForDecoding($imagePath);
        $context = [
            'source_dimensions' => $sourceInfo ? "{$sourceInfo[0]}x{$sourceInfo[1]}" : 'unreadable',
            'source_bytes' => @filesize($imagePath) ?: null,
            'resized' => $decodePath !== $imagePath,
        ];

        try {
            $text = (new QrReader($decodePath))->text();
        } catch (Throwable $e) {
            Log::warning('Aadhaar QR decode threw', $context + ['error' => $e->getMessage()]);

            return null;
        } finally {
            if ($decodePath !== $imagePath) {
                @unlink($decodePath);
            }
        }

        // "No QR found" is not an exception here — QrReader::decode() catches its own
        // NotFoundException/FormatException/ChecksumException internally and leaves
        // text() returning the boolean `false` it was seeded with, not a string.
        // filled(false) is true in Laravel (booleans are never "blank"), so a plain
        // filled() check here would have let that `false` through as if it were text.
        $found = is_string($text) && $text !== '';

        if ($found) {
            Log::info('Aadhaar QR decoded', $context + ['payload_bytes' => strlen($text)]);
        } else {
            Log::warning('Aadhaar QR not found in photo', $context);
        }

        return $found ? $text : null;
    }

    /**
     * Returns a path to a decode-sized copy of the image, or the original path
     * unchanged if it's already small enough or couldn't be read/resized — QrReader
     * gets the chance to try the original either way rather than failing here.
     */
    private function downscaleForDecoding(string $imagePath): string
    {
        $info = @getimagesize($imagePath);

        if (! $info) {
            return $imagePath;
        }

        [$width, $height, $type] = $info;

        if ($width <= self::MAX_DECODE_DIMENSION && $height <= self::MAX_DECODE_DIMENSION) {
            return $imagePath;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG => @imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imagePath) : false,
            default => false,
        };

        if (! $source) {
            return $imagePath;
        }

        $scale = self::MAX_DECODE_DIMENSION / max($width, $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        // unset(), not imagedestroy() — GD images became plain refcounted GdImage
        // objects in PHP 8, and imagedestroy() has been a documented no-op ever since
        // (it's kept only so old code doesn't fatal calling it). Freeing $source here,
        // before imagejpeg() below allocates the encode buffer for $resized, is the
        // only thing that actually drops the original full-size bitmap early instead
        // of leaving it held until the function returns.
        unset($source);

        // Not tempnam(): it creates the file it names, and appending '.jpg' after the
        // fact would leave that empty placeholder behind unlinked while imagejpeg()
        // writes to a second, different path instead.
        $tempPath = sys_get_temp_dir() . '/aadhaar_qr_' . bin2hex(random_bytes(8)) . '.jpg';
        imagejpeg($resized, $tempPath, 85);
        unset($resized);

        return $tempPath;
    }
}
