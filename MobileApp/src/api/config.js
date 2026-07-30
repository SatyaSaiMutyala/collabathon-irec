import {Platform} from 'react-native';

/**
 * Where the Laravel API lives.
 *
 * Default path is loopback plus `adb reverse tcp:<DEV_PORT> tcp:<DEV_PORT>`, which maps
 * the device's own loopback back to this machine and covers emulators and physical
 * devices alike without pinning a machine-specific address into the repo:
 *
 *   adb reverse tcp:8001 tcp:8001
 *
 * When that tunnel is unavailable — it dies silently if the adb server restarts, and
 * `adb reverse --list` keeps listing the mapping afterwards, so a dead tunnel looks
 * identical to a live one — set LAN_HOST to this machine's Wi-Fi address and serve with
 * `php artisan serve --host=0.0.0.0` so the device can reach it directly. Leave it null
 * to use loopback. It is machine-specific: do not commit a value you did not set.
 */
const LAN_HOST = '192.168.29.137';

const DEV_HOST =
  LAN_HOST ??
  Platform.select({
    ios: '127.0.0.1',
    android: '127.0.0.1',
    default: '127.0.0.1',
  });

// `php artisan serve` for collabathon-backend. Port 8000 is a different project.
const DEV_PORT = 8001;

export const API_BASE_URL = __DEV__
  ? `http://${DEV_HOST}:${DEV_PORT}/api/v1`
  : 'https://api.irec.ae/api/v1';

/** Matches the server-side cap in HandlesListQueries. */
export const DEFAULT_PAGE_SIZE = 20;

export const REQUEST_TIMEOUT_MS = 20000;
