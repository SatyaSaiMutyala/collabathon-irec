import React, {useEffect, useState} from 'react';
import {ActivityIndicator, View} from 'react-native';
import {NavigationContainer, createNavigationContainerRef} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {attachPushHandlers, registerDevice} from '../services/push';
import {usePrimePermissions} from '../hooks/usePrimePermissions';
import {loadAuthState} from '../store/authPersistence';
import {fetchMe, hydrateAuth, sessionExpired} from '../store/slices/authSlice';
import {useAppTheme} from '../theme';
import AuthNavigator from './AuthNavigator';
import BrokerTabNavigator from './BrokerTabNavigator';
import DeveloperTabNavigator from './DeveloperTabNavigator';
import DeveloperProfileScreen from '../screens/broker/DeveloperProfileScreen';
import ProjectDetailScreen from '../screens/broker/ProjectDetailScreen';
import MapPickerScreen from '../screens/broker/MapPickerScreen';
import PropertyLeadsScreen from '../screens/developer/PropertyLeadsScreen';
import BrokerDetailScreen from '../screens/developer/BrokerDetailScreen';
import NotificationsScreen from '../screens/shared/NotificationsScreen';
import NotificationDetailScreen from '../screens/shared/NotificationDetailScreen';
import ProjectTermsScreen from '../screens/shared/ProjectTermsScreen';

const BrokerStack = createNativeStackNavigator();
const DeveloperStack = createNativeStackNavigator();

const BrokerRootStack = () => (
  <BrokerStack.Navigator screenOptions={{headerShown: false}}>
    <BrokerStack.Screen name="Main" component={BrokerTabNavigator} />
    <BrokerStack.Screen
      name="DeveloperProfile"
      component={DeveloperProfileScreen}
      options={{animation: 'slide_from_right'}}
    />
    <BrokerStack.Screen
      name="ProjectDetail"
      component={ProjectDetailScreen}
      options={{animation: 'slide_from_right'}}
    />
    <BrokerStack.Screen
      name="Notifications"
      component={NotificationsScreen}
      options={{animation: 'slide_from_right'}}
    />
    <BrokerStack.Screen
      name="NotificationDetail"
      component={NotificationDetailScreen}
      options={{animation: 'slide_from_right'}}
    />
    <BrokerStack.Screen
      name="MapPicker"
      component={MapPickerScreen}
      options={{animation: 'slide_from_bottom'}}
    />
    {/* Registered on both stacks: PropertyDetailBody is shared, so the terms button
        has to resolve whichever role opened the project. */}
    <BrokerStack.Screen
      name="ProjectTerms"
      component={ProjectTermsScreen}
      options={{animation: 'slide_from_right'}}
    />
  </BrokerStack.Navigator>
);

const DeveloperRootStack = () => (
  <DeveloperStack.Navigator screenOptions={{headerShown: false}}>
    <DeveloperStack.Screen name="Main" component={DeveloperTabNavigator} />
    <DeveloperStack.Screen
      name="BrokerDetail"
      component={BrokerDetailScreen}
      options={{animation: 'slide_from_right'}}
    />
    <DeveloperStack.Screen
      name="PropertyLeads"
      component={PropertyLeadsScreen}
      options={{animation: 'slide_from_right'}}
    />
    <DeveloperStack.Screen
      name="Notifications"
      component={NotificationsScreen}
      options={{animation: 'slide_from_right'}}
    />
    <DeveloperStack.Screen
      name="NotificationDetail"
      component={NotificationDetailScreen}
      options={{animation: 'slide_from_right'}}
    />
    {/* Registered on both stacks: PropertyDetailBody is shared, so the terms button
        has to resolve whichever role opened the project. */}
    <DeveloperStack.Screen
      name="ProjectTerms"
      component={ProjectTermsScreen}
      options={{animation: 'slide_from_right'}}
    />
  </DeveloperStack.Navigator>
);

/**
 * A ref rather than the `navigation` prop: a notification can be tapped from a cold
 * start, before any screen has mounted to hand a prop to.
 */
export const navigationRef = createNavigationContainerRef();

/** Where each server-side notification type lands when tapped. */
const PUSH_ROUTES = {
  request_received: data => ['BrokerDetail', {leadId: Number(data.lead_id)}],
  request_accepted: () => ['Main', {screen: 'PartnersTab'}],
  request_declined: () => ['Main', {screen: 'RequestsTab'}],
  broker_registered: () => ['Main', {screen: 'RequestBrokersTab'}],
  property_assigned: () => ['Main', {screen: 'PropertiesTab'}],
  // Not dead code: the 3-step registration wizard issues a real Sanctum token from
  // step 1 onward (see AuthController::startRegistration), which stays live straight
  // through `pending`, so a broker waiting on a decision is a genuine signed-in
  // session that can register a device and receive this. 'Welcome' — not 'Login',
  // which is the *developer's* email+password screen and something a broker never
  // sees at all — is the broker's own entry point, the same place a normal
  // pending/rejected sign-in response already lands them.
  broker_approved: () => ['Welcome'],
  broker_rejected: () => ['Welcome'],
  // Straight to the broadcast that was tapped, so the full image and text are the
  // first thing seen. Falls back to the list for a push sent before the payload
  // carried an id — those are already installed on devices, and dropping the user on
  // the history is better than a detail screen with nothing to show.
  announcement: data =>
    data.announcement_id
      ? ['NotificationDetail', {announcementId: Number(data.announcement_id)}]
      : ['Notifications'],
};

/**
 * Types that mean "this session is no longer valid" rather than "go look at
 * something" — an admin reset the password or paused an already-active account.
 * Same call client.js's 401 interceptor makes: this is not a navigation target, it's
 * a reason to end the session right now instead of waiting for the next API request
 * to 401 and do it then.
 */
const SESSION_KILLING_PUSH_TYPES = ['password_reset', 'broker_revoked'];

const RootNavigator = () => {
  const dispatch = useAppDispatch();
  const {colors} = useAppTheme();
  const isLoggedIn = useAppSelector(state => state.auth.isLoggedIn);
  const role = useAppSelector(state => state.auth.role);
  const registrationStatus = useAppSelector(state => state.auth.registrationStatus);
  const [isSessionRestored, setIsSessionRestored] = useState(false);

  // Fires on every launch — whether or not the device is already signed in, and
  // regardless of login state, so it's asked once up front rather than the first time
  // a form happens to need one deep in registration.
  usePrimePermissions();

  useEffect(() => {
    loadAuthState().then(persisted => {
      if (persisted?.token) {
        // hydrateAuth also re-arms the HTTP client with the stored token.
        dispatch(hydrateAuth(persisted));
        // Re-validate against the server: the account may have been paused or
        // rejected since last launch, in which case the 401 interceptor signs out.
        dispatch(fetchMe());
      }
      setIsSessionRestored(true);
    });
  }, [dispatch]);

  // Registration follows the session, not the mount: a device belongs to whoever is
  // signed in on it, so signing in claims it and signing out releases it.
  //
  // try/catch, not `.catch()`. `registerDevice()` reaches Firebase through `getApp()`,
  // which throws *synchronously* when no native default app is configured — so it can
  // fail before it ever returns a promise, and a trailing `.catch` never runs. That
  // throw happened inside the effect, on the exact tick `isLoggedIn` flipped, which is
  // why signing in took the whole app down rather than just losing notifications.
  // Nothing here is worth interrupting a sign-in for: a denied permission, an offline
  // device or an unconfigured Firebase project all mean "no push", not "no app".
  useEffect(() => {
    if (!isLoggedIn) {
      return;
    }

    (async () => {
      try {
        await registerDevice();
      } catch {
        // Intentionally silent — see above.
      }
    })();
  }, [isLoggedIn]);

  // Tap handling is attached once, for the life of the app. It has to survive the stack
  // swapping between auth and role navigators, which is why it is not inside a screen.
  useEffect(() => {
    return attachPushHandlers(data => {
      if (SESSION_KILLING_PUSH_TYPES.includes(data?.type)) {
        dispatch(sessionExpired());
        return;
      }

      const route = PUSH_ROUTES[data?.type]?.(data);
      if (route && navigationRef.isReady()) {
        navigationRef.navigate(...route);
      }
    });
    // `dispatch` is referentially stable (Redux Toolkit), so this still attaches once
    // for the life of the app, same as before.
  }, [dispatch]);

  if (!isSessionRestored) {
    return (
      <View
        style={{
          flex: 1,
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: colors.background,
        }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  let content;
  if (isLoggedIn && role === 'developer') {
    content = <DeveloperRootStack />;
  } else if (isLoggedIn && role === 'broker' && registrationStatus === 'draft') {
    // Mid-registration — this is what makes reopening the app land directly on
    // whichever wizard step was last saved, instead of restarting from Welcome.
    content = <AuthNavigator initialRouteName="CompleteProfile" />;
  } else if (isLoggedIn && role === 'broker') {
    content = <BrokerRootStack />;
  } else if (role === 'broker' && registrationStatus === 'pendingApproval') {
    // Only matters for a *cold* relaunch that lands here directly (submitted, then
    // force-quit before ever seeing the confirmation) — registrationStatus/role/
    // isLoggedIn are all persisted (see store/index.js), so this branch can be true
    // on the very first render, before any navigator exists yet, which is the one
    // case `initialRouteName` actually takes effect. It does nothing for the live,
    // in-app transition (draft -> pendingApproval while already mounted): that's
    // still the same <AuthNavigator/> component at the same tree position, so React
    // treats it as a prop update rather than a remount, and a prop change to an
    // already-mounted navigator's initialRouteName is a no-op. CompleteProfileScreen
    // handles that case itself with a direct `navigation.replace('PendingApproval')`.
    content = <AuthNavigator initialRouteName="PendingApproval" />;
  } else {
    content = <AuthNavigator />;
  }

  return <NavigationContainer ref={navigationRef}>{content}</NavigationContainer>;
};

export default RootNavigator;
