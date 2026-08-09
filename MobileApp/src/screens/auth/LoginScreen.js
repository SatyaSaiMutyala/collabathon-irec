import React, {useEffect, useState} from 'react';
import {TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AuthHeader, Badge, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearAuthError, login} from '../../store/slices/authSlice';

/**
 * Developer sign-in — email + password, issued by an admin. Channel partners no
 * longer use this screen at all: their sign-in is mobile number + OTP
 * (MobileOtpLoginScreen -> OtpVerifyScreen), with no password on either side of the
 * API. Developer accounts keep a password because they are provisioned once by an
 * admin rather than self-registered, so a credential handed over up front is the
 * right shape for that role.
 */
const LoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const status = useAppSelector(state => state.auth.status);
  const serverError = useAppSelector(state => state.auth.error);
  const fieldErrors = useAppSelector(state => state.auth.fieldErrors);

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
    dispatch(login({email: email.trim(), password, role: 'developer'}));
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
            icon="business-outline"
            eyebrow="DEVELOPER SIGN-IN"
            title="Log in to Collabathon"
            subtitle="Sign in with the credentials the Collabathon team issued for your account."
          />

          {serverError && status === 'failed' && (
            <View style={{alignItems: 'center', marginTop: -spacing.md, marginBottom: spacing.md}}>
              <Badge label="Sign-in failed" tone="danger" />
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
          label={isSubmitting ? 'Signing in…' : 'Log In'}
          onPress={handleLogin}
          disabled={isSubmitting}
          style={{marginTop: spacing.sm}}
        />
      </KeyboardAwareScrollView>
    </ScreenContainer>
  );
};

export default LoginScreen;
