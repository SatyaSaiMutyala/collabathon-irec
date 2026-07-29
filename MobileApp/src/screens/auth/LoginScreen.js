import React, {useEffect, useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Badge, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearAuthError, login} from '../../store/slices/authSlice';

/**
 * Broker sign-in. Email is the login key — the API authenticates on email + password,
 * and the mobile number is profile data only.
 *
 * The approval gate is enforced by the server: a pending broker gets a 403 and no
 * token, which surfaces here as the "awaiting approval" state rather than an error.
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
    dispatch(login({email: email.trim(), password, role: 'broker'}));
  };

  const passwordError =
    localError || fieldErrors?.password?.[0] || fieldErrors?.email?.[0] || serverError;

  return (
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        enableResetScrollToCoords={false}
        keyboardShouldPersistTaps="handled">
        <View style={{marginBottom: spacing.xxl}}>
          <AppText variant="overline" color={colors.primary}>
            WELCOME BACK
          </AppText>
          <AppText variant="display" style={{marginTop: spacing.xxs}}>
            Log in to Collabathon
          </AppText>
          <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
            Sign in with the email and password from your approved registration.
          </AppText>

          {registrationStatus === 'pendingApproval' && (
            <View style={{marginTop: spacing.md}}>
              <Badge label="Approval pending" tone="warning" />
            </View>
          )}
          {registrationStatus === 'rejected' && (
            <View style={{marginTop: spacing.md}}>
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
          label={isSubmitting ? 'Signing in…' : 'Log In'}
          onPress={handleLogin}
          disabled={isSubmitting}
          style={{marginTop: spacing.sm}}
        />

        <View style={styles.footerRow}>
          <AppText variant="body" color={colors.textSecondary}>
            New broker?{' '}
          </AppText>
          <TouchableOpacity onPress={() => navigation.navigate('Register')} hitSlop={8}>
            <AppText variant="bodyMedium" color={colors.primary}>
              Create an account
            </AppText>
          </TouchableOpacity>
        </View>
      </KeyboardAwareScrollView>
    </ScreenContainer>
  );
};

const styles = StyleSheet.create({
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: moderateScale(20),
  },
});

export default LoginScreen;
