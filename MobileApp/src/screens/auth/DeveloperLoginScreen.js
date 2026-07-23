import React, {useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch} from '../../store/hooks';
import {logInAsDeveloper} from '../../store/slices/authSlice';

const DEMO_DEVELOPER = {
  developerId: 'dev-1',
  contactName: 'Ahmed Al Suwaidi',
  mobile: '+971502345678',
  email: 'ahmed@aurumestates.com',
};

const DeveloperLoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const [mobile, setMobile] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState();

  const handleLogin = () => {
    if (!mobile.trim() || !password.trim()) {
      setError('Enter your mobile number and password.');
      return;
    }
    setError(undefined);
    dispatch(
      logInAsDeveloper({
        developerId: 'dev-1',
        contactName: 'Developer',
        mobile: mobile.trim(),
        email: '',
      }),
    );
  };

  const handleDemoLogin = () => {
    dispatch(logInAsDeveloper(DEMO_DEVELOPER));
  };

  return (
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        keyboardShouldPersistTaps="handled">
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10} style={{marginBottom: spacing.lg}}>
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
            Developer accounts are created by the Admin. Sign in with the mobile number and
            password you were issued.
          </AppText>
        </View>

        <Input
          label="Mobile Number"
          placeholder="Enter Mobile Number"
          leftIcon="call-outline"
          keyboardType="phone-pad"
          value={mobile}
          onChangeText={setMobile}
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
          label="Continue with Demo Account"
          variant="outline"
          icon="flash-outline"
          onPress={handleDemoLogin}
        />
      </KeyboardAwareScrollView>
    </ScreenContainer>
  );
};

const styles = StyleSheet.create({
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

export default DeveloperLoginScreen;
