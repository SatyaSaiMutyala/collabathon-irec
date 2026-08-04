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
const LAN_HOST = '192.168.1.2';

const DEV_HOST =
  LAN_HOST ??
  Platform.select({
    ios: '127.0.0.1',
    android: '127.0.0.1',
    default: '127.0.0.1',
  });

// `php artisan serve` for collabathon-backend, which binds 127.0.0.1:8000 by default.
const DEV_PORT = 8000;

// The __DEV__ branch resolves to exactly the same loopback URL a hardcoded string gave,
// so debug builds are unchanged — but a release build has to reach the real API, and a
// literal here silently shipped 127.0.0.1 to production.
// Every endpoint in api/endpoints.js is written relative — '/auth/login', '/leads' — so
// the version prefix belongs here. Without it those resolve to /auth/login on the host
// root, which Laravel answers with a 404 the client reports as "cannot reach the server".
// export const API_BASE_URL = 'https://brown-hedgehog-768805.hostingersite.com/api/v1';

// LOCAL DEV — pointed at `php artisan serve --host=0.0.0.0` on this machine (192.168.1.2).
// Uncomment the line above and delete this one to go back to the live server.
export const API_BASE_URL = `http://${DEV_HOST}:${DEV_PORT}/api/v1`;

/** Matches the server-side cap in HandlesListQueries. */
export const DEFAULT_PAGE_SIZE = 20;

export const REQUEST_TIMEOUT_MS = 20000;
