import React, {useEffect, useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Badge, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearAuthError, login} from '../../store/slices/authSlice';

/**
 * Shared sign-in for both mobile roles. Email is the login key — the API authenticates
 * on email + password alone and answers with the account's own role, which RootNavigator
 * uses to pick the broker or developer stack. No role is sent with the request: an email
 * belongs to exactly one account, so asking the client to declare the role up front only
 * creates a way to be wrong about it.
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
    // No `role` — the server resolves it from the account and we route on the answer.
    dispatch(login({email: email.trim(), password}));
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
          {navigation.canGoBack() && (
            <TouchableOpacity
              onPress={() => navigation.goBack()}
              hitSlop={10}
              style={{alignSelf: 'flex-start', marginBottom: spacing.lg}}>
              {/* iOS uses a chevron for back, not Material's full arrow — this matches
                  RegisterScreen, DeveloperLoginScreen and PropertyHero. */}
              <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
            </TouchableOpacity>
          )}

          <AppText variant="overline" color={colors.primary}>
            WELCOME BACK
          </AppText>
          <AppText variant="display" style={{marginTop: spacing.xxs}}>
            Log in to Collabathon
          </AppText>
          <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
            One sign-in for channel partners and developers — we'll take you to the right
            place based on your account.
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
            New channel partner?{' '}
          </AppText>
          <TouchableOpacity onPress={() => navigation.navigate('Register')} hitSlop={8}>
            <AppText variant="bodyMedium" color={colors.primary}>
              Create an account
            </AppText>
          </TouchableOpacity>
        </View>

        {/* Developers are onboarded by an admin, so there's no self-serve path to offer. */}
        <AppText
          variant="caption"
          color={colors.textMuted}
          align="center"
          style={{marginTop: spacing.sm}}>
          Developer accounts are created by the Collabathon team.
        </AppText>
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
