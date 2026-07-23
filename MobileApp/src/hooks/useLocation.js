import {useCallback, useState} from 'react';
import {Platform, PermissionsAndroid} from 'react-native';
import Geolocation from '@react-native-community/geolocation';

async function requestAndroidPermission() {
  if (Platform.OS !== 'android') {
    return true;
  }
  const granted = await PermissionsAndroid.request(
    PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
  );
  return granted === PermissionsAndroid.RESULTS.GRANTED;
}

async function reverseGeocode(lat, lon) {
  const response = await fetch(
    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=10&addressdetails=1`,
    {headers: {'User-Agent': 'CollabathonApp/1.0'}},
  );
  const json = await response.json();
  const address = json?.address ?? {};
  const city = address.city || address.town || address.state || 'Unknown';
  return {city, label: city};
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
