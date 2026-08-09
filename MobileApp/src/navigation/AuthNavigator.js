import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import MobileOtpLoginScreen from '../screens/auth/MobileOtpLoginScreen';
import OtpVerifyScreen from '../screens/auth/OtpVerifyScreen';
import CompleteProfileScreen from '../screens/auth/CompleteProfileScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import PendingApprovalScreen from '../screens/auth/PendingApprovalScreen';

const Stack = createNativeStackNavigator();

/**
 * Two separate paths off Welcome, matching the two different sign-in mechanisms:
 *
 *   Channel partner — MobileOtpLogin -> OtpVerify -> (existing account) signed in
 *                      by RootNavigator reacting to isLoggedIn, or (new number)
 *                      CompleteProfile -> PendingApproval.
 *   Developer       — Login (email + password, admin-provisioned account).
 */
const AuthNavigator = () => (
  <Stack.Navigator
    screenOptions={{headerShown: false, animation: 'slide_from_right'}}
    initialRouteName="Welcome">
    <Stack.Screen name="Welcome" component={WelcomeScreen} options={{animation: 'fade'}} />
    <Stack.Screen name="MobileOtpLogin" component={MobileOtpLoginScreen} />
    <Stack.Screen name="OtpVerify" component={OtpVerifyScreen} />
    <Stack.Screen name="CompleteProfile" component={CompleteProfileScreen} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="PendingApproval" component={PendingApprovalScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
