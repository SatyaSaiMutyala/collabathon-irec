import React, {useEffect, useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearAuthError, login} from '../../store/slices/authSlice';

/**
 * Developer sign-in. Accounts are issued by the admin — there is no self-registration
 * path — and the credential is the email the admin entered when creating the company.
 */
const DeveloperLoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const status = useAppSelector(state => state.auth.status);
  const serverError = useAppSelector(state => state.auth.error);
  const fieldErrors = useAppSelector(state => state.auth.fieldErrors);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [localError, setLocalError] = useState();

  const isSubmitting = status === 'loading';

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
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        keyboardShouldPersistTaps="handled">
        <TouchableOpacity
          onPress={() => navigation.goBack()}
          hitSlop={10}
          style={{marginBottom: spacing.lg}}>
          <Icon name="chevron-back" size={moderateScale(22)} color={colors.textPrimary} />
        </TouchableOpacity>

        <View style={{marginBottom: spacing.xxl}}>
          <AppText variant="overline" color={colors.primary}>
            DEVELOPER / BUILDER
          </AppText>
          <AppText variant="display" style={{marginTop: spacing.xxs}}>
            Log in to your account
          </AppText>
          <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
            Developer accounts are created by the Admin. Sign in with the email and password
            you were issued.
          </AppText>
        </View>

        <Input
          label="Email"
          placeholder="you@company.ae"
          leftIcon="mail-outline"
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          value={email}
          onChangeText={setEmail}
        />
        <Input
          label="Password"
          placeholder="Enter Password"
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

const styles = StyleSheet.create({});

export default DeveloperLoginScreen;
