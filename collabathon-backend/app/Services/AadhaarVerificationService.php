<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use Zxing\QrReader;

/**
 * Verifies a channel partner's Aadhaar from a photo of the physical card, not an
 * uploaded XML/eAadhaar file — see the decision behind this: the Complete Profile form
 * already asks for a photo of the card (the same field every other document uses), and
 * changing that to a file picker for a separately-downloaded XML/PDF was a bigger
 * mobile change than reusing what's already there.
 *
 * The trade-off is explicit: this only works if the photo's QR code is sharp and
 * complete enough to decode, which a card photo is not guaranteed to be the way a
 * dedicated QR scan or a downloaded XML always would be. `verifyFromImage()` reports
 * that failure distinctly from a Surepass rejection, so the caller can tell "couldn't
 * read the QR in this photo" apart from "read it fine, but Surepass says it's invalid".
 */
class AadhaarVerificationService
{
    public function __construct(private SurepassClient $client)
    {
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
     * Null, not an exception — an unreadable QR is an expected, common outcome for a
     * card photo (glare, blur, a corner cut off), not a system failure.
     */
    private function decodeQr(string $imagePath): ?string
    {
        try {
            $text = (new QrReader($imagePath))->text();
        } catch (Throwable $e) {
            Log::info('Aadhaar QR decode failed', ['error' => $e->getMessage()]);

            return null;
        }

        // "No QR found" is not an exception here — QrReader::decode() catches its own
        // NotFoundException/FormatException/ChecksumException internally and leaves
        // text() returning the boolean `false` it was seeded with, not a string.
        // filled(false) is true in Laravel (booleans are never "blank"), so a plain
        // filled() check here would have let that `false` through as if it were text.
        return is_string($text) && $text !== '' ? $text : null;
    }
}
