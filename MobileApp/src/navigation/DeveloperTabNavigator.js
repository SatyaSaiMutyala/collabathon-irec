import React from 'react';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';
import DashboardScreen from '../screens/developer/DashboardScreen';
import MyPropertiesScreen from '../screens/developer/MyPropertiesScreen';
import RequestBrokersScreen from '../screens/developer/RequestBrokersScreen';
import ProfileScreen from '../screens/developer/ProfileScreen';
import {useTabBarScreenOptions} from './tabBarOptions';

const Tab = createBottomTabNavigator();

const TAB_ICONS = {
  DashboardTab: {active: 'stats-chart', inactive: 'stats-chart-outline'},
  PropertiesTab: {active: 'business', inactive: 'business-outline'},
  RequestBrokersTab: {active: 'paper-plane', inactive: 'paper-plane-outline'},
  ProfileTab: {active: 'person', inactive: 'person-outline'},
};

const DeveloperTabNavigator = () => {
  const screenOptions = useTabBarScreenOptions(TAB_ICONS);

  return (
    <Tab.Navigator screenOptions={screenOptions}>
      <Tab.Screen name="DashboardTab" component={DashboardScreen} options={{title: 'Dashboard'}} />
      <Tab.Screen name="PropertiesTab" component={MyPropertiesScreen} options={{title: 'Properties'}} />
      <Tab.Screen
        name="RequestBrokersTab"
        component={RequestBrokersScreen}
        options={{title: 'Requests'}}
      />
      <Tab.Screen name="ProfileTab" component={ProfileScreen} options={{title: 'Profile'}} />
    </Tab.Navigator>
  );
};

export default DeveloperTabNavigator;
