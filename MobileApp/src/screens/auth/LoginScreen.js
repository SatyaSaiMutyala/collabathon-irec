import React, {useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Badge, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {approveRegistration, logIn, submitRegistration} from '../../store/slices/authSlice';

const DEMO_BROKER = {
  suffix: 'Mr.',
  fullNameAsRera: 'Rachid Al Mansoori',
  mobileNumber: '+971501234567',
  alternateMobile: '',
  emailId: 'rachid@primerealty.com',
  residenceAddress: 'Marina Walk, Dubai Marina, Dubai, UAE',
  photoAttachment: '',
};

const LoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const registrationStatus = useAppSelector(state => state.auth.registrationStatus);

  const [mobile, setMobile] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState();

  const handleLogin = () => {
    if (registrationStatus !== 'approved') {
      setError(
        registrationStatus === 'pendingApproval'
          ? 'Your registration is still awaiting admin approval.'
          : 'No approved account found. Please register first.',
      );
      return;
    }
    if (!mobile.trim() || !password.trim()) {
      setError('Enter your mobile number and password.');
      return;
    }
    setError(undefined);
    dispatch(logIn());
  };

  const handleDemoLogin = () => {
    dispatch(submitRegistration(DEMO_BROKER));
    dispatch(approveRegistration());
    dispatch(logIn());
  };

  return (
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        keyboardShouldPersistTaps="handled">
        <View style={{marginBottom: spacing.xxl}}>
          <AppText variant="overline" color={colors.primary}>
            WELCOME BACK
          </AppText>
          <AppText variant="display" style={{marginTop: spacing.xxs}}>
            Log in to Collabathon
          </AppText>
          <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
            Sign in with the mobile number and password from your approved registration.
          </AppText>

          {registrationStatus === 'pendingApproval' && (
            <View style={{marginTop: spacing.md}}>
              <Badge label="Approval pending" tone="warning" />
            </View>
          )}
        </View>

        <Input
          label="Mobile Number"
          placeholder="e.g. +971 50 123 4567"
          leftIcon="call-outline"
          keyboardType="phone-pad"
          value={mobile}
          onChangeText={setMobile}
        />
        <Input
          label="Password"
          placeholder="Enter your password"
          leftIcon="lock-closed-outline"
          isPassword
          value={password}
          onChangeText={setPassword}
          error={error}
        />

        <Button label="Log In" onPress={handleLogin} style={{marginTop: spacing.sm}} />

        <View style={styles.dividerRow}>
          <View style={[styles.dividerLine, {backgroundColor: colors.border}]} />
          <AppText variant="caption" color={colors.textMuted} style={styles.dividerLabel}>
            OR
          </AppText>
          <View style={[styles.dividerLine, {backgroundColor: colors.border}]} />
        </View>

        <Button
          label="Continue with Demo Account"
          variant="outline"
          icon="flash-outline"
          onPress={handleDemoLogin}
        />

        <View style={styles.footerRow}>
          <AppText variant="body" color={colors.textSecondary}>
            New broker?{' '}
          </AppText>
          <TouchableOpacity onPress={() => navigation.replace('Register')} hitSlop={8}>
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
  dividerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: moderateScale(16),
  },
  dividerLine: {
    flex: 1,
    height: 1,
  },
  dividerLabel: {
    marginHorizontal: moderateScale(10),
  },
});

export default LoginScreen;
