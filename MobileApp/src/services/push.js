import {Platform} from 'react-native';
import {getApp} from '@react-native-firebase/app';
import {
  AuthorizationStatus,
  getInitialNotification,
  getMessaging,
  getToken,
  onMessage,
  onNotificationOpenedApp,
  requestPermission,
  setBackgroundMessageHandler,
} from '@react-native-firebase/messaging';
import notifee, {AndroidImportance, EventType} from '@notifee/react-native';
import {pushApi} from '../api/endpoints';

/**
 * Push notifications — permission, token registration, and display.
 *
 * Modular API throughout: `messaging().onMessage(...)` and every other namespaced call
 * logs "deprecated (as well as all React Native Firebase namespaced API) and will be
 * removed in the next major release" on v22+. The modular equivalents take the messaging
 * instance as their first argument and are the form that survives the next major.
 *
 * Split from the store on purpose: everything here talks to the OS, and the store owns
 * state. The one piece of shared state — the token we last registered — lives in module
 * scope because it has to survive a sign-out (we need it to *unregister*) and it is not
 * something any screen renders.
 */

/**
 * Android needs a channel before it will show anything; posting to a channel that does
 * not exist is silently dropped. `default` matches the `channel_id` the server sets on
 * every message (App\Services\Fcm::message).
 */
const CHANNEL_ID = 'default';

let currentToken = null;

/** Resolved lazily — the default app is configured natively, before any JS runs. */
const messaging = () => getMessaging(getApp());

async function ensureChannel() {
  if (Platform.OS !== 'android') {
    return;
  }

  await notifee.createChannel({
    id: CHANNEL_ID,
    name: 'General',
    // HIGH is what makes it a heads-up banner rather than a silent tray entry.
    importance: AndroidImportance.HIGH,
  });
}

/**
 * Asks the OS, then returns whether we may notify.
 *
 * iOS shows the system prompt here. Android 13+ shows one too; below 33 it resolves
 * granted without a prompt, which is why the result is checked rather than assumed.
 */
export async function requestPushPermission() {
  const status = await requestPermission(messaging());

  return (
    status === AuthorizationStatus.AUTHORIZED || status === AuthorizationStatus.PROVISIONAL
  );
}

/**
 * Registers this device against the signed-in account.
 *
 * Safe to call on every sign-in and on every app start while signed in — the server
 * upserts on the token, so repeats are free and a token that rotated gets moved to the
 * right user rather than leaving a stale row behind.
 */
export async function registerDevice() {
  if (!(await requestPushPermission())) {
    return null;
  }

  await ensureChannel();

  // No registerDeviceForRemoteMessages() here. On iOS the FCM token is only issued once
  // APNs hands one over, but RNFirebase registers for that itself unless
  // `messaging_ios_auto_register_for_remote_messages` is turned off in firebase.json —
  // there is no firebase.json in this project, so it is on. Calling it anyway is a no-op
  // that logs "Usage of messaging().registerDeviceForRemoteMessages() is not required"
  // on every sign-in. If auto-registration is ever disabled, this has to come back.

  const token = await getToken(messaging());
  if (!token) {
    return null;
  }

  currentToken = token;
  await pushApi.registerDevice({token, platform: Platform.OS});

  return token;
}

/**
 * Drops this device from the account, before the auth token is revoked.
 *
 * Order matters: the request needs the Sanctum token that logging out is about to
 * delete, so this has to run first. If it fails the row is left behind, which is why the
 * server also prunes tokens FCM reports as unregistered.
 */
export async function unregisterDevice() {
  const token = currentToken ?? (await getToken(messaging()).catch(() => null));
  if (!token) {
    return;
  }

  await pushApi.forgetDevice({token}).catch(() => {});
  currentToken = null;
}

/**
 * Wires the three delivery paths and returns an unsubscribe.
 *
 * @param {(data: object) => void} onOpen  called when a notification is *tapped*, with
 *   the message's data payload — the caller decides where that navigates.
 */
export function attachPushHandlers(onOpen) {
  // `messaging()` resolves through getApp(), which throws synchronously when no native
  // Firebase app is configured. This runs from an effect at startup, so letting it throw
  // would take the app down over notifications — see the note at the registerDevice call
  // site in RootNavigator.
  let app;
  try {
    app = messaging();
  } catch {
    return () => {};
  }

  // 1. Foreground. FCM deliberately does not draw anything while the app is open, so
  //    notifee draws it — otherwise a message arriving mid-session is invisible.
  const unsubscribeForeground = onMessage(app, async message => {
    await ensureChannel();
    await notifee.displayNotification({
      title: message.notification?.title,
      body: message.notification?.body,
      data: message.data ?? {},
      android: {channelId: CHANNEL_ID, pressAction: {id: 'default'}},
    });
  });

  // 2. Tapped while the app was backgrounded.
  const unsubscribeOpened = onNotificationOpenedApp(app, message => {
    onOpen?.(message?.data ?? {});
  });

  // 3. Tapped while the app was closed. This resolves once, with the message that
  //    launched the process — null on a normal cold start.
  getInitialNotification(app).then(message => {
    if (message) {
      onOpen?.(message.data ?? {});
    }
  });

  // notifee owns the tap for the banner *it* drew in case 1; FCM never sees that one.
  const unsubscribeNotifee = notifee.onForegroundEvent(({type, detail}) => {
    if (type === EventType.PRESS) {
      onOpen?.(detail.notification?.data ?? {});
    }
  });

  return () => {
    unsubscribeForeground();
    unsubscribeOpened();
    unsubscribeNotifee();
  };
}

/**
 * Registered at module scope in index.js, outside React.
 *
 * A data message arriving while the app is killed runs this in a headless task; there is
 * no component tree to hang it off, and Firebase warns if it is missing.
 */
export function registerBackgroundHandler() {
  // Runs at module scope in index.js, before React exists — an unconfigured Firebase app
  // would throw here and the process would die with no screen ever drawn.
  let app;
  try {
    app = messaging();
  } catch {
    return;
  }

  setBackgroundMessageHandler(app, async () => {
    // The `notification` block on the payload means the OS already drew the banner.
    // Nothing to do here — this exists so the runtime has a handler to call.
  });
}
