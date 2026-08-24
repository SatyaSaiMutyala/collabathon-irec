<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aadhaar verification via DigiLocker — a real, live UIDAI-backed check (the
 * broker signs into DigiLocker with their own Aadhaar-linked mobile + OTP),
 * unlike the offline QR/XML/eAadhaar-upload paths this app tried first and
 * abandoned when Surepass's scope for those was never enabled. "Digilocker Via
 * Link" is Active on this account, so this is the one Aadhaar method that
 * actually works today.
 *
 * Three calls, one flow:
 *   1. initialize()     — opens a session, hands back a URL to load in a WebView.
 *   2. status()          — polled after the WebView reports the redirect happened,
 *                           to confirm DigiLocker itself finished (not just that
 *                           the WebView navigated somewhere).
 *   3. downloadAadhaar() — once status() says completed, pulls the actual
 *                           verified Aadhaar data back.
 */
class DigilockerVerificationService
{
    /**
     * Must be a public https:// URL Surepass's own DigiLocker page can redirect a
     * real device's browser to once the broker finishes — not this backend's own
     * APP_URL, which in local development is an unreachable loopback address, and
     * the redirect happens from Surepass's externally-hosted page straight to the
     * device's browser, never through this app's own dev tunnel. The mobile app
     * watches its WebView for navigation to this exact URL as the "the flow just
     * finished" signal; nothing on this backend ever receives a request at it.
     */
    private const REDIRECT_URL = 'https://brown-hedgehog-768805.hostingersite.com/digilocker/callback';

    public function __construct(private SurepassClient $client)
    {
    }

    /**
     * @return array{status: 'initialized'|'unavailable', client_id?: string, url?: string, expiry_seconds?: int|float, message?: string}
     */
    public function initialize(?string $fullName, ?string $mobile, ?string $email): array
    {
        $prefill = array_filter([
            'full_name' => $fullName,
            'mobile_number' => $mobile,
            'user_email' => $email,
        ]);

        try {
            $response = $this->client->postJson('/api/v1/digilocker/initialize', [
                'data' => array_filter([
                    'signup_flow' => true,
                    'redirect_url' => self::REDIRECT_URL,
                    'skip_main_screen' => true,
                    'prefill_options' => $prefill ?: null,
                ]),
            ]);
        } catch (Throwable $e) {
            Log::error('Surepass DigiLocker initialize unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not start DigiLocker verification. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('DigiLocker initialize rejected by Surepass', ['message' => $response->json('message')]);

            return [
                'status' => 'unavailable',
                'message' => $response->json('message') ?? 'Could not start DigiLocker verification.',
            ];
        }

        return [
            'status' => 'initialized',
            'client_id' => $response->json('data.client_id'),
            'url' => $response->json('data.url'),
            'expiry_seconds' => $response->json('data.expiry_seconds'),
        ];
    }

    /**
     * @return array{status: 'completed'|'pending'|'failed'|'unavailable', aadhaar_linked?: bool, message?: string}
     */
    public function status(string $clientId): array
    {
        try {
            $response = $this->client->getJson("/api/v1/digilocker/status/{$clientId}");
        } catch (Throwable $e) {
            Log::error('Surepass DigiLocker status unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not check verification status right now.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return [
                'status' => 'unavailable',
                'message' => $response->json('message') ?? 'Could not check verification status right now.',
            ];
        }

        if ($response->json('data.failed') === true) {
            return [
                'status' => 'failed',
                'message' => $response->json('data.error_description') ?? 'DigiLocker verification failed.',
            ];
        }

        if ($response->json('data.completed') !== true) {
            return ['status' => 'pending'];
        }

        return [
            'status' => 'completed',
            'aadhaar_linked' => (bool) $response->json('data.aadhaar_linked'),
        ];
    }

    /**
     * @return array{status: 'verified'|'rejected'|'unavailable', data?: array, message?: string}
     */
    public function downloadAadhaar(string $clientId): array
    {
        try {
            $response = $this->client->getJson("/api/v1/digilocker/download-aadhaar/{$clientId}");
        } catch (Throwable $e) {
            Log::error('Surepass DigiLocker download-aadhaar unreachable', ['error' => $e->getMessage()]);

            return [
                'status' => 'unavailable',
                'message' => 'Could not fetch the verified Aadhaar data. You can continue — this can be verified later.',
            ];
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::info('DigiLocker download-aadhaar rejected by Surepass', ['message' => $response->json('message')]);

            return [
                'status' => 'rejected',
                'message' => $response->json('message') ?? 'Could not retrieve Aadhaar details.',
            ];
        }

        $xml = $response->json('data.aadhaar_xml_data') ?? [];

        return [
            'status' => 'verified',
            'data' => [
                'name' => $xml['full_name'] ?? $response->json('data.digilocker_metadata.name'),
                'dob' => $xml['dob'] ?? $response->json('data.digilocker_metadata.dob'),
                'gender' => $xml['gender'] ?? $response->json('data.digilocker_metadata.gender'),
                'masked_aadhaar' => $xml['masked_aadhaar'] ?? null,
                'address' => $xml['full_address'] ?? null,
                // A temporary, pre-signed link to the actual UIDAI-signed XML file —
                // the controller downloads it server-side and re-hosts it as a real
                // Upload row; this raw URL is not meant to reach the mobile app.
                'xml_url' => $response->json('data.xml_url'),
            ],
        ];
    }
}
