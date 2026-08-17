import {useEffect} from 'react';
import {Platform} from 'react-native';
import {PERMISSIONS, RESULTS, request} from 'react-native-permissions';

/**
 * The camera/photo-library/location permissions every attachment field and the
 * location picker eventually need, one for each platform's actual permission name.
 */
const PERMISSIONS_TO_PRIME = Platform.select({
  ios: [PERMISSIONS.IOS.CAMERA, PERMISSIONS.IOS.PHOTO_LIBRARY, PERMISSIONS.IOS.LOCATION_WHEN_IN_USE],
  android: [
    PERMISSIONS.ANDROID.CAMERA,
    PERMISSIONS.ANDROID.READ_MEDIA_IMAGES,
    PERMISSIONS.ANDROID.ACCESS_FINE_LOCATION,
  ],
  default: [],
});

/**
 * Asks for camera/photo-library/location access up front, on first launch, rather
 * than the OS prompt landing mid-form the first time someone taps an attachment box
 * deep in registration. `request()` only actually shows the system dialog the first
 * time it's called for a given permission — once granted, denied, or blocked, every
 * later call (including next app launch) just resolves with the stored status, so
 * this is safe to run unconditionally on every mount rather than needing a "have we
 * asked before" flag of our own.
 */
export function usePrimePermissions() {
  useEffect(() => {
    PERMISSIONS_TO_PRIME.forEach(permission => {
      request(permission).catch(() => {
        // A permission that errors out (missing usage-description string, unsupported
        // OS version) just means that one capability degrades gracefully later, at the
        // point of use — not something to interrupt app launch over.
      });
    });
  }, []);
}

export {RESULTS};
