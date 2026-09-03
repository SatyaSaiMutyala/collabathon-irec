<?php

namespace App\Services;

use App\Support\MasterDataSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the irecexpo.com "Master Data" API — a feed of developer/project
 * registrations an admin browses on the Master Data page and can convert into a real
 * Developer account (see MasterDataController).
 *
 * The API is entirely external and outside our control: every call can fail for
 * reasons that have nothing to do with this app (their server down, key rotated,
 * network blip), so every method here returns a result array with an `ok` flag rather
 * than throwing — a controller checks `ok` and shows a plain "couldn't reach Master
 * Data" state instead of a 500.
 */
class MasterDataClient
{
    private const TIMEOUT_SECONDS = 15;

    /**
     * One page of registrations.
     *
     * @param  array<string,mixed>  $params  Any of: page, limit, search, city, status,
     *                                       dev, type, bhk, sort, order — passed straight
     *                                       through, all optional. Every one of these is
     *                                       a real, working filter on the vendor's side
     *                                       (verified against the live API), not just
     *                                       documented and ignored.
     * @return array{ok:bool, records:array, pagination:array, error:?string}
     */
    public function list(array $params = []): array
    {
        if (! MasterDataSettings::isConfigured()) {
            return $this->failure('Master Data API key is not configured — add it in Settings.');
        }

        $query = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $query['format'] = 'collabathon';

        try {
            $response = $this->request()->get(MasterDataSettings::baseUrl(), $query);
        } catch (\Throwable $e) {
            Log::error('Master Data API request threw', ['error' => $e->getMessage()]);

            return $this->failure('Could not reach the Master Data API.');
        }

        if (! $response->successful()) {
            Log::error('Master Data API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->failure($response->json('message') ?? "Master Data API returned HTTP {$response->status()}.");
        }

        $body = $response->json();

        if (! ($body['ok'] ?? false)) {
            return $this->failure($body['message'] ?? 'Master Data API reported an error.');
        }

        return [
            'ok' => true,
            'records' => $body['data'] ?? [],
            'pagination' => $body['pagination'] ?? [],
            'error' => null,
        ];
    }

    /**
     * Best-effort lookup of one registration by id, for a direct link or an expired
     * cache entry (see MasterDataController::record()) — there is no single-record
     * endpoint (a `?id=` param is silently ignored by the vendor's API, confirmed
     * against the live service), so this pages through results looking for a match.
     * Bounded to a few pages: a normal visit resolves through the cache MasterData's
     * index() populates, this only runs on a cold cache and is not worth an unbounded
     * scan of the vendor's entire dataset.
     */
    public function find(int $registrationId): ?array
    {
        $maxPages = 10;
        $limit = 100;

        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->list(['page' => $page, 'limit' => $limit]);

            if (! $result['ok']) {
                return null;
            }

            foreach ($result['records'] as $record) {
                if ((int) ($record['registration_id'] ?? 0) === $registrationId) {
                    return $record;
                }
            }

            if (($result['pagination']['has_next_page'] ?? false) !== true) {
                break;
            }
        }

        return null;
    }

    private function request()
    {
        return Http::withHeaders(['X-API-KEY' => MasterDataSettings::apiKey()])
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson();
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'records' => [], 'pagination' => [], 'error' => $message];
    }
}
