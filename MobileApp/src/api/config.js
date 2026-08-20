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
// LAN mode: `php artisan serve --host 0.0.0.0 --port 8000`, reached directly over
// Wi-Fi at this machine's own address — no adb reverse tunnel involved, so this
// still works even when the tunnel keeps dying. Machine-specific — update this (or
// set back to null to use the adb-reverse/loopback path) whenever the Mac's Wi-Fi
// IP changes.
const LAN_HOST = '192.168.1.4';

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
// Currently 8000 to match the LAN server this is pointed at above — this machine also
// serves `pace-backend` on 8000 sometimes, which answers every Collabathon request with
// its own sign-in page instead, so if that's running again switch this back to 8001 (and
// serve Collabathon with `--port 8001` instead) to avoid the collision.
const DEV_PORT = 8000;

// Every endpoint in api/endpoints.js is written relative — '/auth/login', '/leads' — so
// the version prefix belongs here. Without it those resolve to /auth/login on the host
// root, which Laravel answers with a 404 the client reports as "cannot reach the server".
// PRODUCTION — the live domain. Uncomment this (and comment the local-dev line below)
// for any build that ships, App Store submissions included.
// export const API_BASE_URL = 'https://brown-hedgehog-768805.hostingersite.com/api/v1';

// LOCAL DEV — `php artisan serve --port=8001`, reached through the adb tunnel. Swap
// this back in (and comment the production line above) to point at this machine instead.
export const API_BASE_URL = `http://${DEV_HOST}:${DEV_PORT}/api/v1`;

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
