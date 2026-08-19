import {Platform} from 'react-native';

/**
 * Where the Laravel API lives.
 *
 * Default path is loopback plus `adb reverse tcp:<DEV_PORT> tcp:<DEV_PORT>`, which maps
 * the device's own loopback back to this machine and covers emulators and physical
 * devices alike without pinning a machine-specific address into the repo:
 *
 *   adb reverse tcp:8000 tcp:8000
 *
 * When that tunnel is unavailable — it dies silently if the adb server restarts, and
 * `adb reverse --list` keeps listing the mapping afterwards, so a dead tunnel looks
 * identical to a live one — set LAN_HOST to this machine's Wi-Fi address and serve with
 * `php artisan serve --host=0.0.0.0` so the device can reach it directly. Leave it null
 * to use loopback. It is machine-specific: do not commit a value you did not set.
 */
// Back to loopback + `adb reverse tcp:8001 tcp:8001`. The LAN address (192.168.29.164)
// is reachable in principle, but this machine's Wi-Fi is on Windows' Public profile,
// which blocks inbound 8001 — and opening it needs an elevated firewall rule. The adb
// tunnel needs no admin and no firewall change, so it is the path of least resistance
// here. Set this to the Wi-Fi address only if the tunnel is unavailable.
const LAN_HOST = null;

const DEV_HOST =
  LAN_HOST ??
  Platform.select({
    ios: '127.0.0.1',
    android: '127.0.0.1',
    default: '127.0.0.1',
  });

// Must match both the running `php artisan serve --port=…` AND the backend's APP_URL —
// asset()/Storage::url() build every image and document URL from APP_URL, so a mismatch
// here hands the device working API responses with broken (unreachable-host) media links.
// Only read by the local-dev line below.
//
// 8001, not 8000: this machine already serves `pace-backend` on 8000, which answers every
// Collabathon request with its own sign-in page. Collabathon's APP_URL is :8001 and that
// is where it must be served from.
const DEV_PORT = 8001;

// Every endpoint in api/endpoints.js is written relative — '/auth/login', '/leads' — so
// the version prefix belongs here. Without it those resolve to /auth/login on the host
// root, which Laravel answers with a 404 the client reports as "cannot reach the server".
// PRODUCTION — the live domain. Uncomment this (and comment the local-dev line below)
// for any build that ships, App Store submissions included.
export const API_BASE_URL = 'https://brown-hedgehog-768805.hostingersite.com/api/v1';

// LOCAL DEV — `php artisan serve --port=8001`, reached through the adb tunnel. Swap
// this back in (and comment the production line above) to point at this machine instead.
// export const API_BASE_URL = `http://${DEV_HOST}:${DEV_PORT}/api/v1`;

/** Matches the server-side cap in HandlesListQueries. */
export const DEFAULT_PAGE_SIZE = 20;

export const REQUEST_TIMEOUT_MS = 20000;

/**
 * A multipart request carrying one or more picked files (profile photo, PAN/
 * Aadhaar/RERA/GST scans) needs longer than a plain JSON call — step 3 of
 * registration can ship up to four attachments in one request, and the same
 * 20s cap that suits a JSON body was cutting that upload off mid-flight on a
 * slow or lossy connection, which surfaced as "Submit for approval" silently
 * not navigating (the request aborted with ECONNABORTED before the server
 * ever answered).
 */
export const UPLOAD_TIMEOUT_MS = 60000;
