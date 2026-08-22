import {Platform} from 'react-native';

/**
 * A human-readable name for this handset, sent as `device_name` on every sign-in.
 *
 * Sanctum stores it as the token's name, and the admin panel's CP tab renders it as
 * "which device is this partner signed in on" — so it has to mean something to a person
 * reading a list, not just to a developer. Every path used to send the literal string
 * "mobile", which made that column useless.
 *
 * Read from `Platform.constants` rather than react-native-device-info: Android already
 * exposes Brand/Model/Release there, and pulling in a native dependency to reformat
 * three values it already has is not worth the build cost. iOS exposes no model at all
 * without a native module, so it names the OS and version and stops there — good enough
 * to tell an iPhone session apart from an Android one, which is all this is for.
 */
export function deviceName() {
  const c = Platform.constants ?? {};

  if (Platform.OS === 'android') {
    // Brand is often already the first word of Model on some vendors ("Pixel 7" on a
    // brand of "google"), so they are only joined when the model doesn't already say it.
    const brand = (c.Brand ?? '').trim();
    const model = (c.Model ?? '').trim();
    const hardware = model.toLowerCase().startsWith(brand.toLowerCase())
      ? model
      : [brand, model].filter(Boolean).join(' ');

    const os = c.Release ? `Android ${c.Release}` : 'Android';

    return [hardware || 'Android device', os].join(' · ');
  }

  const version = c.osVersion ?? Platform.Version;
  return version ? `iPhone · iOS ${version}` : 'iPhone';
}
