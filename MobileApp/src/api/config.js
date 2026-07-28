import {Platform} from 'react-native';

/**
 * Where the Laravel API lives.
 *
 * The host differs per platform in development:
 *   - iOS simulator shares the Mac's loopback, so 127.0.0.1 works.
 *   - Android emulator cannot see the host's localhost; 10.0.2.2 is the alias
 *     the emulator maps to the host machine.
 *
 * On a physical device neither works — set DEV_HOST to the Mac's LAN IP.
 */
const DEV_HOST = Platform.select({
  ios: '127.0.0.1',
  android: '10.0.2.2',
  default: '127.0.0.1',
});

const DEV_PORT = 8000;

export const API_BASE_URL = __DEV__
  ? `http://${DEV_HOST}:${DEV_PORT}/api/v1`
  : 'https://api.irec.ae/api/v1';

/** Matches the server-side cap in HandlesListQueries. */
export const DEFAULT_PAGE_SIZE = 20;

export const REQUEST_TIMEOUT_MS = 20000;
