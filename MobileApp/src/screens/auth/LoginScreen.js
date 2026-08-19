import React, {useEffect, useState} from 'react';
import {TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {AppText, AuthHeader, Badge, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearAuthError, login} from '../../store/slices/authSlice';

/**
 * Shared sign-in for both mobile roles. Email is the login key — the API
 * authenticates on email + password alone and answers with the account's own role,
 * which RootNavigator uses to pick the broker or developer stack. No role is sent
 * with the request: an email belongs to exactly one account, so asking the client to
 * declare the role up front only creates a way to be wrong about it.
 *
 * The approval gate is enforced by the server: a pending or rejected broker gets a
 * 403 and no token, which surfaces here as a status badge rather than a generic error.
 */
const LoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const status = useAppSelector(state => state.auth.status);
  const serverError = useAppSelector(state => state.auth.error);
  const fieldErrors = useAppSelector(state => state.auth.fieldErrors);
  const registrationStatus = useAppSelector(state => state.auth.registrationStatus);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [localError, setLocalError] = useState();

  const isSubmitting = status === 'loading';

  // Clear any stale error when the screen is first shown.
  useEffect(() => {
    dispatch(clearAuthError());
  }, [dispatch]);

  const handleLogin = () => {
    if (!email.trim() || !password) {
      setLocalError('Enter your email and password.');
      return;
    }
    setLocalError(undefined);
    // No `role` — the server resolves it from the account and we route on the answer.
    dispatch(login({email: email.trim(), password}));
  };

  const passwordError =
    localError || fieldErrors?.password?.[0] || fieldErrors?.email?.[0] || serverError;

  return (
    <ScreenContainer edges={['top', 'bottom']} glow>
      {navigation.canGoBack() && (
        <TouchableOpacity
          onPress={() => navigation.goBack()}
          hitSlop={10}
          style={{alignSelf: 'flex-start', marginTop: spacing.xs, marginBottom: spacing.xs}}>
          {/* iOS uses a chevron for back, not Material's full arrow — this matches
              CompleteProfileScreen, MobileOtpLoginScreen and PropertyHero. */}
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
      )}

      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        enableResetScrollToCoords={false}
        keyboardShouldPersistTaps="handled">
        <View style={{marginTop: spacing.xl, marginBottom: spacing.xxl}}>
          <AuthHeader
            icon="log-in-outline"
            eyebrow="SIGN IN"
            title="Log in to Collabathon"
            subtitle="A private network for HRA's premium developers and partners."
          />

          {registrationStatus === 'pendingApproval' && (
            <View style={{alignItems: 'center', marginTop: -spacing.md, marginBottom: spacing.md}}>
              <Badge label="Approval pending" tone="warning" />
            </View>
          )}
          {registrationStatus === 'rejected' && (
            <View style={{alignItems: 'center', marginTop: -spacing.md, marginBottom: spacing.md}}>
              <Badge label="Registration not approved" tone="danger" />
            </View>
          )}
        </View>

        <Input
          label="Email"
          placeholder="you@company.com"
          leftIcon="mail-outline"
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          value={email}
          onChangeText={setEmail}
        />
        <Input
          label="Password"
          placeholder="Enter your password"
          leftIcon="lock-closed-outline"
          isPassword
          value={password}
          onChangeText={setPassword}
          error={passwordError}
        />

        <Button
          label={isSubmitting ? 'Signing in…' : 'Continue'}
          onPress={handleLogin}
          disabled={isSubmitting}
          style={{
            marginTop: spacing.sm,
            shadowColor: '#000',
            shadowOffset: {width: 0, height: 2},
            shadowOpacity: 0.15,
            shadowRadius: 6,
            elevation: 4,
          }}
        />

        {/* No "New channel partner? Create an account" link here any more — this is
            the developer-only sign-in screen (email + password); a channel partner's
            self-serve path starts at Welcome's own "Channel partners" card, which
            routes into the right OTP flow first (see AuthNavigator's docblock). */}
        {/* Developers are onboarded by an admin, so there's no self-serve path to offer. */}
        <AppText
          variant="caption"
          color={colors.textMuted}
          align="center"
          style={{marginTop: spacing.sm}}>
          Developer accounts are created by the Collabathon team.
        </AppText>

        {/* Stated at the point of consent, per the language pass. */}
        <AppText
          variant="caption"
          color={colors.textMuted}
          style={{marginTop: spacing.md, textAlign: 'center'}}>
          By continuing, you agree to HRA's Partner Network terms.
        </AppText>
      </KeyboardAwareScrollView>
    </ScreenContainer>
  );
};

export default LoginScreen;
