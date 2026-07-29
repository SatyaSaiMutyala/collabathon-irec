import React, {useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Badge, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {approveRegistration, logIn, logInAsDeveloper, submitRegistration} from '../../store/slices/authSlice';

const DEMO_BROKER = {
  suffix: 'Mr.',
  fullNameAsRera: 'Niklish Reddy',
  mobileNumber: '+971501234567',
  alternateMobile: '',
  emailId: 'niklish@primerealty.com',
  residenceAddress: 'Marina Walk, Dubai Marina, Dubai, UAE',
  photoAttachment: '',
};

const DEMO_DEVELOPER = {
  developerId: 'dev-1',
  contactName: 'Satya Mutyala',
  mobile: '+971502345678',
  email: 'satya@aurumestates.com',
};

const LoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const registrationStatus = useAppSelector(state => state.auth.registrationStatus);
  const developerProfile = useAppSelector(state => state.auth.developer);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState();

  const handleLogin = () => {
    const enteredEmail = email.trim().toLowerCase();
    if (!enteredEmail || !password.trim()) {
      setError('Enter your email and password.');
      return;
    }

    // Developer accounts are created by Admin, not self-registered — recognize this device's
    // known developer email (or the canonical demo one) and route there automatically.
    const knownDeveloperEmail = (developerProfile?.email ?? DEMO_DEVELOPER.email).toLowerCase();
    if (enteredEmail === knownDeveloperEmail) {
      setError(undefined);
      dispatch(logInAsDeveloper(developerProfile ?? DEMO_DEVELOPER));
      return;
    }

    if (registrationStatus === 'guest') {
      navigation.navigate('Register');
      return;
    }
    if (registrationStatus !== 'approved') {
      setError(
        registrationStatus === 'pendingApproval'
          ? 'Your registration is still awaiting admin approval.'
          : 'No approved account found. Please register first.',
      );
      return;
    }
    setError(undefined);
    dispatch(logIn());
  };

  const handleDemoBroker = () => {
    dispatch(submitRegistration(DEMO_BROKER));
    dispatch(approveRegistration());
    dispatch(logIn());
  };

  const handleDemoDeveloper = () => {
    dispatch(logInAsDeveloper(DEMO_DEVELOPER));
  };

  return (
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        keyboardShouldPersistTaps="handled">
        <View style={{marginBottom: spacing.xxl}}>
          <AppText variant="overline" color={colors.primary}>
            WELCOME TO COLLABATHON
          </AppText>
          <AppText variant="display" style={{marginTop: spacing.xxs}}>
            Log in to continue
          </AppText>
          <AppText variant="body" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
            Enter your email and password — we'll take you to the right dashboard.
          </AppText>

          {registrationStatus === 'pendingApproval' && (
            <View style={{marginTop: spacing.md}}>
              <Badge label="Broker approval pending" tone="warning" />
            </View>
          )}
        </View>

        <Input
          label="Email"
          placeholder="Enter Email"
          leftIcon="mail-outline"
          keyboardType="email-address"
          autoCapitalize="none"
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
          label="Continue with Demo Broker Account"
          variant="outline"
          icon="flash-outline"
          onPress={handleDemoBroker}
        />
        <Button
          label="Continue with Demo Developer Account"
          variant="outline"
          icon="flash-outline"
          onPress={handleDemoDeveloper}
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
