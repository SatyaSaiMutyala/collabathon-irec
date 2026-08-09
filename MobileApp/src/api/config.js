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
const DEV_PORT = 8000;

// Every endpoint in api/endpoints.js is written relative — '/auth/login', '/leads' — so
// the version prefix belongs here. Without it those resolve to /auth/login on the host
// root, which Laravel answers with a 404 the client reports as "cannot reach the server".
// export const API_BASE_URL = 'https://brown-hedgehog-768805.hostingersite.com/api/v1';

// TEMPORARY — the mobile+OTP sign-in work (migration, AuthController::sendOtp/
// verifyOtp, routes) exists only on this machine so far, not yet deployed to the
// Hostinger host above. Pointed at local `php artisan serve` (already running on
// :8000) so the flow can actually be tested end to end. Swap the line above back in
// once that backend work is deployed and migrated on Hostinger — don't ship a build
// with this line active, 127.0.0.1 only resolves on this Mac.
export const API_BASE_URL = `http://${DEV_HOST}:${DEV_PORT}/api/v1`;

/** Matches the server-side cap in HandlesListQueries. */
export const DEFAULT_PAGE_SIZE = 20;

export const REQUEST_TIMEOUT_MS = 20000;
