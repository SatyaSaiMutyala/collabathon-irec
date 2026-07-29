import React, {useEffect, useState} from 'react';
import {ActivityIndicator, View} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {loadAuthState} from '../store/authPersistence';
import {hydrateAuth} from '../store/slices/authSlice';
import {useAppTheme} from '../theme';
import AuthNavigator from './AuthNavigator';
import BrokerTabNavigator from './BrokerTabNavigator';
import DeveloperTabNavigator from './DeveloperTabNavigator';
import DeveloperProfileScreen from '../screens/broker/DeveloperProfileScreen';
import ProjectDetailScreen from '../screens/broker/ProjectDetailScreen';
import PropertyLeadsScreen from '../screens/developer/PropertyLeadsScreen';
import BrokerProfileScreen from '../screens/developer/BrokerProfileScreen';
import NotificationsScreen from '../screens/shared/NotificationsScreen';

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
  </BrokerStack.Navigator>
);

const DeveloperRootStack = () => (
  <DeveloperStack.Navigator screenOptions={{headerShown: false}}>
    <DeveloperStack.Screen name="Main" component={DeveloperTabNavigator} />
    <DeveloperStack.Screen
      name="PropertyLeads"
      component={PropertyLeadsScreen}
      options={{animation: 'slide_from_right'}}
    />
    <DeveloperStack.Screen
      name="BrokerProfile"
      component={BrokerProfileScreen}
      options={{animation: 'slide_from_right'}}
    />
    <DeveloperStack.Screen
      name="Notifications"
      component={NotificationsScreen}
      options={{animation: 'slide_from_right'}}
    />
  </DeveloperStack.Navigator>
);

const RootNavigator = () => {
  const dispatch = useAppDispatch();
  const {colors} = useAppTheme();
  const isLoggedIn = useAppSelector(state => state.auth.isLoggedIn);
  const role = useAppSelector(state => state.auth.role);
  const [isSessionRestored, setIsSessionRestored] = useState(false);

  useEffect(() => {
    loadAuthState().then(persisted => {
      if (persisted) {
        dispatch(hydrateAuth(persisted));
      }
      setIsSessionRestored(true);
    });
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
  } else if (isLoggedIn && role === 'broker') {
    content = <BrokerRootStack />;
  } else {
    content = <AuthNavigator />;
  }

  return <NavigationContainer>{content}</NavigationContainer>;
};

export default RootNavigator;
