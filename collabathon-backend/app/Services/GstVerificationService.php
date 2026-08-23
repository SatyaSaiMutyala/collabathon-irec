<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GST Advance lookup — a GSTIN in, the registered business details on file for it
 * out (legal name, registration date, GSTIN status, taxpayer type, address,
 * contact details). Used to confirm the typed GST number is real and to auto-fill
 * the company name, the same "verify once, trust the client's report" pattern the
 * PAN flow uses.
 */
class GstVerificationService
{
    public function __construct(private SurepassClient $client)
    {
    }

    /**
     * @return array{status: 'verified'|'rejected'|'unavailable', data?: array, message?: string}
     */
    public function verifyAdvance(string $gstin): array
    {
        try {
            $response = $this->client->postJson('/api/v1/corporate/gstin-advanced', [
                'id_number' => $gstin,
            ]);
        } catch (Throwable $e) {
            Log::error('Surepass GST Advance unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not reach the verification service. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::info('GST Advance rejected by Surepass', ['message' => $response->json('message')]);

            return [
                'status' => 'rejected',
                'message' => $response->json('message') ?? 'That GST number did not verify.',
            ];
        }

        return [
            'status' => 'verified',
            'data' => $response->json('data'),
        ];
    }
}
