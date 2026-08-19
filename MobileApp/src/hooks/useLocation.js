import {useCallback, useState} from 'react';
import {Platform, PermissionsAndroid} from 'react-native';
import Geolocation from '@react-native-community/geolocation';
import {reverseGeocode} from '../utils/geocoding';

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
      () => {
        setState(prev => ({...prev, isLoading: false, error: 'Could not fetch location'}));
      },
      {enableHighAccuracy: false, timeout: 10000, maximumAge: 60000},
    );
  }, []);

  const setManualLocation = useCallback(city => {
    setState({label: city, city, isLoading: false, error: null});
  }, []);

  return {...state, detectLocation, setManualLocation};
}
