import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import MobileOtpLoginScreen from '../screens/auth/MobileOtpLoginScreen';
import OtpVerifyScreen from '../screens/auth/OtpVerifyScreen';
import EmailOtpLoginScreen from '../screens/auth/EmailOtpLoginScreen';
import EmailOtpVerifyScreen from '../screens/auth/EmailOtpVerifyScreen';
import CompleteProfileScreen from '../screens/auth/CompleteProfileScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import PendingApprovalScreen from '../screens/auth/PendingApprovalScreen';
import TermsScreen from '../screens/shared/TermsScreen';

const Stack = createNativeStackNavigator();

/**
 * Developers sign in with email + password (LoginScreen); the role is resolved from
 * the account server-side. Channel partners sign in with email + a 4-digit OTP
 * (EmailOtpLogin -> EmailOtpVerify) instead — see AuthController::sendEmailOtp — and
 * self-register with RegisterScreen when no account exists yet for that email.
 * Developer accounts are admin-provisioned, so there is no self-serve path for that
 * role.
 *
 * The mobile-number + OTP path (MobileOtpLogin -> OtpVerify -> CompleteProfile) is
 * still registered below but nothing currently navigates to it — there is no SMS
 * provider wired up (no SMTP/MSG91/etc), so it was superseded by the email version
 * above once Mailjet delivery was in place. Left in rather than deleted so switching
 * back later is a routing change, not a rebuild of the whole flow.
 */
const AuthNavigator = () => (
  <Stack.Navigator
    screenOptions={{headerShown: false, animation: 'slide_from_right'}}
    initialRouteName="Welcome">
    <Stack.Screen name="Welcome" component={WelcomeScreen} options={{animation: 'fade'}} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="EmailOtpLogin" component={EmailOtpLoginScreen} />
    <Stack.Screen name="EmailOtpVerify" component={EmailOtpVerifyScreen} />
    <Stack.Screen name="Register" component={RegisterScreen} />
    <Stack.Screen name="PendingApproval" component={PendingApprovalScreen} />
    <Stack.Screen name="Terms" component={TermsScreen} options={{animation: 'slide_from_bottom'}} />

    {/* Registered, not linked — see the note above. */}
    <Stack.Screen name="MobileOtpLogin" component={MobileOtpLoginScreen} />
    <Stack.Screen name="OtpVerify" component={OtpVerifyScreen} />
    <Stack.Screen name="CompleteProfile" component={CompleteProfileScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
