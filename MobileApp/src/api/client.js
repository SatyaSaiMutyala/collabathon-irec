import axios from 'axios';
import {API_BASE_URL, REQUEST_TIMEOUT_MS} from './config';

/**
 * The single configured HTTP client. Screens never import this directly — only
 * store thunks do, so all network state stays in Redux.
 */
const client = axios.create({
  baseURL: API_BASE_URL,
  timeout: REQUEST_TIMEOUT_MS,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

/**
 * The Sanctum token is held in module scope rather than read from the store, so
 * the interceptor never has to import the store (which would be a require cycle:
 * store -> slice -> client -> store).
 */
let authToken = null;

export function setAuthToken(token) {
  authToken = token || null;
}

export function getAuthToken() {
  return authToken;
}

/** Called when the server rejects our token, so the app can drop to the login screen. */
let onUnauthorized = null;

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler;
}

client.interceptors.request.use(config => {
  if (authToken) {
    config.headers.Authorization = `Bearer ${authToken}`;
  }
  return config;
});

client.interceptors.response.use(
  response => response,
  error => {
    const status = error.response?.status;

    // 401 means the token is gone or revoked (e.g. an admin rejected the broker).
    // Anything still holding it must sign out rather than retry forever.
    if (status === 401) {
      setAuthToken(null);
      onUnauthorized?.();
    }

    return Promise.reject(error);
  },
);

/**
 * Statuses where `data.message` is something this backend deliberately wrote for a
 * user to read — validation errors, "already submitted", "account pending", etc.
 * Every other status (404, 429, 500, 502, 503, ...) is Laravel's or the proxy's own
 * framework text ("The route ... could not be found.", stack traces, gateway HTML),
 * never meant for an end user, so those get a generic, status-appropriate message
 * instead of whatever the server happened to send back.
 */
const TRUSTED_MESSAGE_STATUSES = new Set([401, 403, 422]);

const GENERIC_MESSAGES_BY_STATUS = {
  404: 'This feature is temporarily unavailable. Please try again later.',
  429: 'Too many attempts. Please wait a moment and try again.',
};

function genericMessageForStatus(status) {
  if (GENERIC_MESSAGES_BY_STATUS[status]) {
    return GENERIC_MESSAGES_BY_STATUS[status];
  }
  if (status >= 500) {
    return 'Something went wrong on our end. Please try again shortly.';
  }
  return 'Something went wrong. Please try again.';
}

/**
 * Normalises Laravel's error shapes into one message a screen can show:
 *   422 -> {message, errors: {field: [msg]}}
 *   403 -> {message}
 *   network failure -> no response at all
 */
export function extractError(error) {
  if (!error.response) {
    return {
      message:
        error.code === 'ECONNABORTED'
          ? 'The request timed out. Please try again.'
          : 'Cannot reach the server. Check your connection and try again.',
      errors: {},
      status: 0,
    };
  }

  const {status, data} = error.response;

  return {
    message:
      status === 401
        ? 'Your session has expired. Please sign in again.'
        : TRUSTED_MESSAGE_STATUSES.has(status) && data?.message
          ? data.message
          : genericMessageForStatus(status),
    errors: data?.errors || {},
    status,
  };
}

export default client;
