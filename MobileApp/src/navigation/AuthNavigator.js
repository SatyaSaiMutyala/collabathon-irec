import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import PendingApprovalScreen from '../screens/auth/PendingApprovalScreen';

const Stack = createNativeStackNavigator();

const AuthNavigator = () => (
  <Stack.Navigator
    screenOptions={{headerShown: false, animation: 'slide_from_right'}}
    initialRouteName="Welcome">
    <Stack.Screen name="Welcome" component={WelcomeScreen} options={{animation: 'fade'}} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="Register" component={RegisterScreen} />
    <Stack.Screen name="PendingApproval" component={PendingApprovalScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
