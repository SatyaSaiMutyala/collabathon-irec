import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import RoleSelectScreen from '../screens/auth/RoleSelectScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import PendingApprovalScreen from '../screens/auth/PendingApprovalScreen';
import DeveloperLoginScreen from '../screens/auth/DeveloperLoginScreen';

const Stack = createNativeStackNavigator();

const AuthNavigator = () => (
  <Stack.Navigator screenOptions={{headerShown: false}} initialRouteName="RoleSelect">
    <Stack.Screen name="RoleSelect" component={RoleSelectScreen} />
    <Stack.Screen name="Register" component={RegisterScreen} />
    <Stack.Screen name="PendingApproval" component={PendingApprovalScreen} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="DeveloperLogin" component={DeveloperLoginScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
