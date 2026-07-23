import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import HomeScreen from '../screens/broker/HomeScreen';
import InterestedScreen from '../screens/broker/InterestedScreen';
import ProfileScreen from '../screens/broker/ProfileScreen';
import {useTabBarScreenOptions} from './tabBarOptions';

const Tab = createBottomTabNavigator();

const TAB_ICONS = {
  HomeTab: {active: 'home', inactive: 'home-outline'},
  InterestedTab: {active: 'bookmark', inactive: 'bookmark-outline'},
  ProfileTab: {active: 'person', inactive: 'person-outline'},
};

const BrokerTabNavigator = () => {
  const screenOptions = useTabBarScreenOptions(TAB_ICONS);

  return (
    <Tab.Navigator screenOptions={screenOptions}>
      <Tab.Screen name="HomeTab" component={HomeScreen} options={{title: 'Home'}} />
      <Tab.Screen name="InterestedTab" component={InterestedScreen} options={{title: 'Interested'}} />
      <Tab.Screen name="ProfileTab" component={ProfileScreen} options={{title: 'Profile'}} />
    </Tab.Navigator>
  );
};

export default BrokerTabNavigator;
