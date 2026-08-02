import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import HomeScreen from '../screens/broker/HomeScreen';
import RequestsScreen from '../screens/broker/RequestsScreen';
import PartnersScreen from '../screens/broker/PartnersScreen';
import ProfileScreen from '../screens/broker/ProfileScreen';
import {useTabBarScreenOptions} from './tabBarOptions';

const Tab = createBottomTabNavigator();

const TAB_ICONS = {
  HomeTab: {active: 'home', inactive: 'home-outline'},
  RequestsTab: {active: 'paper-plane', inactive: 'paper-plane-outline'},
  PartnersTab: {active: 'people', inactive: 'people-outline'},
  ProfileTab: {active: 'person', inactive: 'person-outline'},
};

/**
 * Requests and Partners are the two halves of the same flow — a request the broker sent,
 * and the ones a developer took up. The icons mirror the developer side, where the same
 * two tabs mean the same two things from the other direction.
 */
const BrokerTabNavigator = () => {
  const screenOptions = useTabBarScreenOptions(TAB_ICONS);

  return (
    <Tab.Navigator screenOptions={screenOptions}>
      <Tab.Screen name="HomeTab" component={HomeScreen} options={{title: 'Home'}} />
      <Tab.Screen name="RequestsTab" component={RequestsScreen} options={{title: 'Requests'}} />
      <Tab.Screen name="PartnersTab" component={PartnersScreen} options={{title: 'Partners'}} />
      <Tab.Screen name="ProfileTab" component={ProfileScreen} options={{title: 'Profile'}} />
    </Tab.Navigator>
  );
};

export default BrokerTabNavigator;
