<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PAN Comprehensive lookup — a PAN number in, the demographic details on file for
 * it out (name, DOB, gender, address, masked Aadhaar, Aadhaar-linkage status).
 * Used to auto-fill the rest of the empanelment form once a broker types a valid
 * PAN, the same "verify once, trust the client's report" pattern the Aadhaar
 * flow uses.
 */
class PanVerificationService
{
    public function __construct(private SurepassClient $client)
    {
    }

    /**
     * @return array{status: 'verified'|'rejected'|'unavailable', data?: array, message?: string}
     */
    public function verifyComprehensive(string $panNumber): array
    {
        try {
            $response = $this->client->postJson('/api/v1/pan/pan-comprehensive', [
                'id_number' => $panNumber,
                // Tries v1, then v2, then gives up on just the masked-Aadhaar piece
                // rather than failing the whole lookup — Surepass's own docs
                // recommend this exact value for production reliability.
                'masked_aadhaar_variant' => 'v1, v2, empty',
            ]);
        } catch (Throwable $e) {
            Log::error('Surepass PAN Comprehensive unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not reach the verification service. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::info('PAN Comprehensive rejected by Surepass', ['message' => $response->json('message')]);

            return [
                'status' => 'rejected',
                'message' => $response->json('message') ?? 'That PAN number did not verify.',
            ];
        }

        return [
            'status' => 'verified',
            'data' => $response->json('data'),
        ];
    }
}
