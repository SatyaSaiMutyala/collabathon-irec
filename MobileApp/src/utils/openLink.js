import {Alert, Linking} from 'react-native';

/**
 * Opens a URL in whatever handles it, and says so plainly when nothing does.
 *
 * A bare handle is not a URL. Admins type websites and social links either way — the
 * form accepts "@skylinerealty" as readily as "https://…" — so anything without a
 * scheme is given https:// rather than being handed to Linking, which rejects it and
 * leaves a tap doing nothing at all. `mailto:` and `tel:` are left alone.
 */
export async function openLink(url) {
  if (!url) {
    return;
  }

  const trimmed = String(url).trim();
  const target = /^[a-z][a-z0-9+.-]*:/i.test(trimmed)
    ? trimmed
    : `https://${trimmed.replace(/^@/, '')}`;

  try {
    await Linking.openURL(target);
  } catch {
    Alert.alert('Cannot open', 'No app on this device could open that link.');
  }
}
