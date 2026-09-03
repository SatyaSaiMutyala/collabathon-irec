import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import MobileOtpLoginScreen from '../screens/auth/MobileOtpLoginScreen';
import OtpVerifyScreen from '../screens/auth/OtpVerifyScreen';
import EmailOtpLoginScreen from '../screens/auth/EmailOtpLoginScreen';
import EmailOtpVerifyScreen from '../screens/auth/EmailOtpVerifyScreen';
import CompleteProfileScreen from '../screens/auth/CompleteProfileScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import PendingApprovalScreen from '../screens/auth/PendingApprovalScreen';
import RegistrationPreviewScreen from '../screens/auth/RegistrationPreviewScreen';
import TermsScreen from '../screens/shared/TermsScreen';

const Stack = createNativeStackNavigator();

/**
 * Developers sign in with email + password (LoginScreen); the role is resolved from
 * the account server-side. Channel partners sign in with email + a 4-digit OTP
 * (EmailOtpLogin -> EmailOtpVerify) or mobile + a 6-digit OTP (MobileOtpLogin ->
 * OtpVerify) — WelcomeScreen picks which pair to route into per the admin's
 * `cp_login_method` setting (Settings -> Channel Partners), fetched from
 * `GET /config`. Both self-register with CompleteProfileScreen when no account
 * exists yet for that email/mobile — registered once, outside either pair, since it
 * is shared by both. Developer accounts are admin-provisioned, so there is no
 * self-serve path for that role.
 *
 * There is still no real SMS provider wired up (no SMTP/MSG91/etc — see
 * AuthController::exposesOtpCode) — the mobile path only actually completes on a
 * host with OTP_EXPOSE_CODE set, same as it always has. That is a deliverability
 * gap, not a routing one: an admin can switch every broker onto this path today,
 * they just cannot receive a real text yet.
 *
 * `initialRouteName` is a prop, not hardcoded, so RootNavigator can drop a returning
 * `draft` (mid-registration) session straight onto CompleteProfile instead of Welcome
 * — see RootNavigator's `registrationStatus === 'draft'` branch.
 */
const AuthNavigator = ({initialRouteName = 'Welcome'}) => (
  <Stack.Navigator
    screenOptions={{headerShown: false, animation: 'slide_from_right'}}
    initialRouteName={initialRouteName}>
    <Stack.Screen name="Welcome" component={WelcomeScreen} options={{animation: 'fade'}} />
    <Stack.Screen name="Login" component={LoginScreen} />
    <Stack.Screen name="EmailOtpLogin" component={EmailOtpLoginScreen} />
    <Stack.Screen name="EmailOtpVerify" component={EmailOtpVerifyScreen} />
    <Stack.Screen name="CompleteProfile" component={CompleteProfileScreen} />
    <Stack.Screen name="PendingApproval" component={PendingApprovalScreen} />
    <Stack.Screen
      name="RegistrationPreview"
      component={RegistrationPreviewScreen}
      options={{animation: 'slide_from_right'}}
    />
    <Stack.Screen name="Terms" component={TermsScreen} options={{animation: 'slide_from_bottom'}} />

    {/* Registered, not linked — see the note above. */}
    <Stack.Screen name="MobileOtpLogin" component={MobileOtpLoginScreen} />
    <Stack.Screen name="OtpVerify" component={OtpVerifyScreen} />
  </Stack.Navigator>
);

export default AuthNavigator;
