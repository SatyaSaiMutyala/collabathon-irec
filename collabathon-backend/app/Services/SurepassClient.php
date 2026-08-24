<?php

namespace App\Services;

use App\Support\SurepassSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over Surepass's KYC API — resolves the active environment's base URL
 * and Bearer token from {@see SurepassSettings} on every call, so a token saved (or an
 * environment flipped) in the admin panel takes effect on the very next request, with
 * no cache clear and no redeploy — same reasoning as {@see \App\Support\MailSettings::apply()}.
 */
class SurepassClient
{
    private const TIMEOUT_SECONDS = 20;

    /** @throws \RuntimeException when no token is saved for the active environment */
    private function request(): PendingRequest
    {
        $token = SurepassSettings::activeToken();

        if (! $token) {
            throw new \RuntimeException('No Surepass token is configured for the active environment.');
        }

        return Http::baseUrl(SurepassSettings::baseUrl())
            ->withToken($token)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson();
    }

    public function postJson(string $path, array $payload): Response
    {
        return $this->request()->post($path, $payload);
    }

    public function getJson(string $path): Response
    {
        return $this->request()->get($path);
    }

    /**
     * `attach()` is what actually switches the request to multipart — Laravel has no
     * separate `asMultipart()` to pair it with; calling that would be a no-op method
     * that doesn't exist. Every $fields entry rides along as its own form part.
     *
     * @param array<int, array{name: string, contents: string, filename?: string}> $files
     */
    public function postMultipart(string $path, array $fields, array $files = []): Response
    {
        $request = $this->request();

        foreach ($files as $file) {
            $request = $request->attach($file['name'], $file['contents'], $file['filename'] ?? null);
        }

        return $request->post($path, $fields);
    }
}
