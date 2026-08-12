import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import MobileOtpLoginScreen from '../screens/auth/MobileOtpLoginScreen';
import OtpVerifyScreen from '../screens/auth/OtpVerifyScreen';
import CompleteProfileScreen from '../screens/auth/CompleteProfileScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import PendingApprovalScreen from '../screens/auth/PendingApprovalScreen';

const Stack = createNativeStackNavigator();

/**
 * Both mobile roles share one sign-in again — email + password, the server resolves
 * the role from the account (see LoginScreen). Channel partners self-register with
 * RegisterScreen; developer accounts are admin-provisioned, so there is no self-serve
 * path for that role.
 *
 * The mobile-number + OTP path (MobileOtpLogin -> OtpVerify -> CompleteProfile) is
 * still registered below but nothing currently navigates to it — there is no SMS
 * provider wired up (no SMTP/MSG91/etc), which blocks App Store review, so email +
 * password is the path both builds ship on for now. Left in rather than deleted so
 * switching back later is a routing change, not a rebuild of the whole flow.
 */
const AuthNavigator = () => (
  <Stack.Navigator
    screenOptions={{headerShown: false, animation: 'slide_from_right'}}
    initialRouteName="Welcome">
    <Stack.Screen name="Welcome" component={WelcomeScreen} options={{animation: 'fade'}} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="Register" component={RegisterScreen} />
    <Stack.Screen name="PendingApproval" component={PendingApprovalScreen} />

    {/* Registered, not linked — see the note above. */}
    <Stack.Screen name="MobileOtpLogin" component={MobileOtpLoginScreen} />
    <Stack.Screen name="OtpVerify" component={OtpVerifyScreen} />
    <Stack.Screen name="CompleteProfile" component={CompleteProfileScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
