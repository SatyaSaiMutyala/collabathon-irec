<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transport for Firebase Cloud Messaging, HTTP v1.
 *
 * v1, not the legacy `/fcm/send` endpoint — that one was turned off in 2024, and every
 * tutorial still showing a `key=AAAA…` server key is describing a dead API. v1 wants an
 * OAuth2 bearer token minted from the service account, which is what most of this class
 * is doing.
 *
 * No SDK: the whole surface we need is one POST per token plus a JWT exchange, and
 * pulling in google/apiclient for that would be a much larger dependency than the
 * ~60 lines below.
 */
class Fcm
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Access tokens last an hour; re-minting one per send would double every request. */
    private const TOKEN_CACHE_KEY = 'fcm.access_token';
    private const TOKEN_TTL = 3300; // 55 min, leaving headroom before the real expiry

    /**
     * Send one message to many tokens.
     *
     * v1 has no multicast — each token is its own request. Sends are sequential and
     * capped, because this runs inside the web request that triggered it: a broker
     * marking interest should not wait on a fan-out to a developer's twelve devices.
     * Anything larger than a handful of recipients belongs on a queue, and this returns
     * the dead tokens so the caller can prune them.
     *
     * @param  array<int,string>  $tokens
     * @param  array<string,string>  $data  string values only — FCM rejects anything else
     * @return array{sent:int, failed:int, invalid:array<int,string>}
     */
    public function send(array $tokens, string $title, string $body, array $data = [], ?string $image = null): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return ['sent' => 0, 'failed' => 0, 'invalid' => []];
        }

        if (! $this->configured()) {
            // Loud, because the alternative is a deploy where every notification silently
            // does nothing and nothing in the logs says why. Once per process, not per
            // send, so a busy request does not fill the log with the same line.
            static $warned = false;
            if (! $warned) {
                $warned = true;
                Log::warning('FCM is not configured — no push notifications will be sent.', [
                    'expected_at' => storage_path(config('services.fcm.credentials')),
                ]);
            }

            return ['sent' => 0, 'failed' => 0, 'invalid' => []];
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return ['sent' => 0, 'failed' => count($tokens), 'invalid' => []];
        }

        $endpoint = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            $this->credentials()['project_id']
        );

        $sent = 0;
        $failed = 0;
        $invalid = [];

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->timeout(8)
                ->post($endpoint, ['message' => $this->message($token, $title, $body, $data, $image)]);

            if ($response->successful()) {
                $sent++;
                continue;
            }

            $failed++;

            // 404 UNREGISTERED / 400 INVALID_ARGUMENT on the token itself means the app
            // was uninstalled or the token rotated. Those are permanent — the caller
            // deletes them rather than retrying forever.
            $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
            if (in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $invalid[] = $token;
            } else {
                Log::warning('FCM send failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message'),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'invalid' => $invalid];
    }

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    /** @return array<string,mixed> */
    private function message(string $token, string $title, string $body, array $data, ?string $image = null): array
    {
        /*
         * An image has to be declared per-platform as well as at the top level.
         *
         * The top-level `notification.image` is what most clients read, but Android only
         * renders the big-picture style from `android.notification.image`, and iOS needs
         * `apns.fcm_options.image`. Setting one and not the others gives a banner with no
         * picture on the platform that was missed.
         *
         * The URL must be publicly reachable: FCM fetches it from Google's own servers,
         * not from the device, so a localhost or LAN address silently yields a
         * text-only notification.
         */
        $message = [
            'token' => $token,

            // The cross-platform `notification` block is what makes the OS draw the
            // banner while the app is backgrounded, without any JS running.
            'notification' => array_filter(['title' => $title, 'body' => $body, 'image' => $image]),

            // FCM requires every data value to be a string; an int here is a 400.
            'data' => array_map(static fn ($value) => (string) $value, $data),

            'android' => [
                'priority' => 'high',
                'notification' => ['channel_id' => 'default', 'sound' => 'default'],
            ],
            'apns' => [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => 'default', 'content-available' => 1]],
            ],
        ];

        if ($image) {
            $message['android']['notification']['image'] = $image;
            $message['apns']['fcm_options']['image'] = $image;
        }

        return $message;
    }

    /** Mints (and caches) an OAuth2 access token from the service account's JWT. */
    private function accessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL, function () {
            $credentials = $this->credentials();
            $now = time();

            $jwt = $this->signedJwt([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URI,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            if ($jwt === null) {
                return null;
            }

            $response = Http::asForm()->timeout(8)->post(self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('FCM auth failed', ['body' => $response->body()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /** RS256, which is the only algorithm Google's token endpoint accepts here. */
    private function signedJwt(array $claims, string $privateKey): ?string
    {
        $encode = static fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode($claims);

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            Log::error('FCM service account private key could not be read');

            return null;
        }

        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $unsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * The service account, read from disk and held for the request.
     *
     * Kept out of config/ and out of the repo — storage/app is gitignored, so the key
     * cannot be committed by accident. Returns null when it is absent, which is how a
     * developer without the file gets a no-op instead of an exception on every action
     * that happens to notify someone.
     *
     * @return array<string,mixed>|null
     */
    private function credentials(): ?array
    {
        static $cached = false;
        static $value = null;

        if ($cached) {
            return $value;
        }

        $cached = true;
        $path = storage_path(config('services.fcm.credentials'));

        if (! is_readable($path)) {
            return $value = null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $value = is_array($decoded) && isset($decoded['project_id'], $decoded['client_email'], $decoded['private_key'])
            ? $decoded
            : null;
    }
}
