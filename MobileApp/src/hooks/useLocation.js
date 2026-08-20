import {useCallback, useState} from 'react';
import {Alert, Linking, Platform, PermissionsAndroid} from 'react-native';
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

/** Only for POSITION_UNAVAILABLE — a permission denial already has its own OS-level prompt. */
function promptToEnableLocation() {
  Alert.alert(
    'Turn on location',
    'Your device\'s location is turned off, so nearby developers can\'t be shown automatically. Turn it on, then try again.',
    [
      {text: 'Not now', style: 'cancel'},
      {text: 'Open Settings', onPress: openLocationSettings},
    ],
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

  const detectLocation = useCallback(async () => {
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
        if (error.code === POSITION_UNAVAILABLE) {
          promptToEnableLocation();
        }
      },
      {enableHighAccuracy: false, timeout: 10000, maximumAge: 60000},
    );
  }, []);

  const setManualLocation = useCallback(city => {
    setState({label: city, city, isLoading: false, error: null});
  }, []);

  return {...state, detectLocation, setManualLocation};
}
