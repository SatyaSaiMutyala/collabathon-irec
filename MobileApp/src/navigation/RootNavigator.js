import React, {useEffect, useState} from 'react';
import {ActivityIndicator, View} from 'react-native';
import {NavigationContainer, createNavigationContainerRef} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {attachPushHandlers, registerDevice} from '../services/push';
import {loadAuthState} from '../store/authPersistence';
import {fetchMe, hydrateAuth} from '../store/slices/authSlice';
import {useAppTheme} from '../theme';
import AuthNavigator from './AuthNavigator';
import BrokerTabNavigator from './BrokerTabNavigator';
import DeveloperTabNavigator from './DeveloperTabNavigator';
import DeveloperProfileScreen from '../screens/broker/DeveloperProfileScreen';
import ProjectDetailScreen from '../screens/broker/ProjectDetailScreen';
import PropertyLeadsScreen from '../screens/developer/PropertyLeadsScreen';
import BrokerDetailScreen from '../screens/developer/BrokerDetailScreen';
import NotificationsScreen from '../screens/shared/NotificationsScreen';
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
};

const RootNavigator = () => {
  const dispatch = useAppDispatch();
  const {colors} = useAppTheme();
  const isLoggedIn = useAppSelector(state => state.auth.isLoggedIn);
  const role = useAppSelector(state => state.auth.role);
  const [isSessionRestored, setIsSessionRestored] = useState(false);

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
      const route = PUSH_ROUTES[data?.type]?.(data);
      if (route && navigationRef.isReady()) {
        navigationRef.navigate(...route);
      }
    });
  }, []);

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
  } else if (isLoggedIn && role === 'broker') {
    content = <BrokerRootStack />;
  } else {
    content = <AuthNavigator />;
  }

  return <NavigationContainer ref={navigationRef}>{content}</NavigationContainer>;
};

export default RootNavigator;
