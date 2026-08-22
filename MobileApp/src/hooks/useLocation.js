import {useCallback, useEffect, useRef, useState} from 'react';
import {Alert, AppState, Linking, Platform, PermissionsAndroid} from 'react-native';
import Geolocation from '@react-native-community/geolocation';
import {reverseGeocode} from '../utils/geocoding';

/**
 * The standard W3C `PositionError` codes this library reports errors as (it mirrors
 * the web Geolocation API rather than defining its own) — 2 is specifically "no
 * provider could produce a fix", which on Android fires immediately (not after the
 * request's own timeout) when the device's location toggle itself is off, as
 * distinct from 1 (permission) or 3 (a real GPS timeout with services on).
 */
const POSITION_UNAVAILABLE = 2;

/** Deep-links into the OS location settings — there's no in-app way to flip this toggle. */
function openLocationSettings() {
  if (Platform.OS === 'android') {
    Linking.sendIntent('android.settings.LOCATION_SOURCE_SETTINGS');
    return;
  }
  // iOS has no equivalent system-wide deep link a third-party app is allowed to
  // open — this app's own Settings page is the closest, and Location sits right on it.
  Linking.openURL('app-settings:');
}

/**
 * Guards against stacking alerts. Module-scoped, not per-hook: the user should only
 * ever see one of these at a time, and two mounted copies of the hook would otherwise
 * each be entitled to their own.
 */
let promptOpen = false;

/** Only for POSITION_UNAVAILABLE — a permission denial already has its own OS-level prompt. */
function promptToEnableLocation() {
  // A second detection attempt landing while the first alert is still up used to queue
  // a second identical alert behind it, so dismissing one just revealed another.
  if (promptOpen) {
    return;
  }
  promptOpen = true;

  const close = () => {
    promptOpen = false;
  };

  Alert.alert(
    'Turn on location',
    'Your device\'s location is turned off, so nearby developers can\'t be shown automatically. Turn it on, then try again.',
    [
      {text: 'Not now', style: 'cancel', onPress: close},
      {
        text: 'Open Settings',
        onPress: () => {
          close();
          openLocationSettings();
        },
      },
    ],
    // Back-button and tap-outside dismissals bypass the buttons entirely; without this
    // the flag would stay set and no further alert could ever be shown.
    {cancelable: true, onDismiss: close},
  );
}

async function requestAndroidPermission() {
  if (Platform.OS !== 'android') {
    return true;
  }
  const granted = await PermissionsAndroid.request(
    PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
  );
  return granted === PermissionsAndroid.RESULTS.GRANTED;
}

export function useCurrentLocation() {
  const [state, setState] = useState({
    label: 'Set your location',
    city: null,
    isLoading: false,
    error: null,
  });

  /**
   * The current state, readable from inside the AppState subscription below.
   *
   * That subscription is registered once and must not be torn down and re-added on
   * every state change, so it cannot close over `state` directly — it would capture
   * the value from the render that registered it and never see another one.
   */
  const latest = useRef(state);
  latest.current = state;

  /**
   * `silent` suppresses the "Turn on location" alert. Set on the automatic retries
   * below: an alert the user did not ask for, re-appearing every time they switch back
   * to the app, is worse than the missing city it is complaining about. A detection the
   * user actually triggered still explains itself.
   */
  const detectLocation = useCallback(async ({silent = false} = {}) => {
    setState(prev => ({...prev, isLoading: true, error: null}));

    const hasPermission = await requestAndroidPermission();
    if (!hasPermission) {
      setState(prev => ({...prev, isLoading: false, error: 'Location permission denied'}));
      return;
    }

    Geolocation.getCurrentPosition(
      async position => {
        try {
          const {city, label} = await reverseGeocode(
            position.coords.latitude,
            position.coords.longitude,
          );
          setState({label, city, isLoading: false, error: null});
        } catch {
          setState(prev => ({...prev, isLoading: false, error: 'Could not resolve location'}));
        }
      },
      error => {
        setState(prev => ({...prev, isLoading: false, error: 'Could not fetch location'}));
        if (error.code === POSITION_UNAVAILABLE && !silent) {
          promptToEnableLocation();
        }
      },
      {enableHighAccuracy: false, timeout: 10000, maximumAge: 60000},
    );
  }, []);

  /**
   * Re-detects whenever the app returns to the foreground still without a city.
   *
   * Turning the device's location toggle on is a change this app is never told about,
   * and HomeScreen detects once on mount into a tab that stays mounted for the whole
   * session — so a broker who enabled location and came back sat looking at "Set your
   * location" with nothing re-checking it.
   *
   * Gated on having no city rather than on how the user got here: they may have used
   * the alert's Settings button, the Settings app directly, or a shortcut, and none of
   * those are distinguishable from inside the app. Once a city is resolved this stops
   * firing entirely, so a normal session pays for at most one extra fix.
   *
   * The one case this cannot catch is the quick-settings shade, which is an overlay
   * rather than another app — Android never reports the app as having left the
   * foreground, so there is no event to react to. The location chip in the header is
   * the manual way back from that.
   */
  useEffect(() => {
    const subscription = AppState.addEventListener('change', next => {
      if (next !== 'active' || latest.current.city || latest.current.isLoading) {
        return;
      }
      detectLocation({silent: true});
    });

    return () => subscription.remove();
  }, [detectLocation]);

  const setManualLocation = useCallback(city => {
    setState({label: city, city, isLoading: false, error: null});
  }, []);

  return {...state, detectLocation, setManualLocation};
}
