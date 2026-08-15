import React, {useCallback, useEffect, useState} from 'react';
import {ActivityIndicator, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, AuthHeader, OtpInput, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {sendEmailOtp, verifyEmailOtp} from '../../store/slices/authSlice';

const CODE_LENGTH = 4;
const RESEND_COOLDOWN_S = 30;

/**
 * Channel-partner sign-in, step 2 of 2 — same fork shape as OtpVerifyScreen, one
 * digit shorter. `verifyEmailOtp`'s own status decides what happens next:
 *
 *   'login'    — RootNavigator swaps to the broker stack the instant `isLoggedIn`
 *                flips; this screen calls no navigation itself.
 *   'register' — no account for this email yet; on to Register, carrying the
 *                verified email so the form can prefill (and lock) that field.
 *   403        — an account exists but isn't approved yet (or was rejected); on to
 *                PendingApproval.
 *   otherwise  — wrong code: shown inline, boxes clear, ready to retry.
 *
 * Unlike OtpVerifyScreen, this one never auto-fills or auto-submits from the API's
 * debug_code — email actually delivers (Mailjet), so the real flow is always "go
 * check your inbox and type the code in", and the screen should behave that way even
 * in a debug build rather than skipping straight past it.
 */
const EmailOtpVerifyScreen = ({navigation, route}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const emailOtp = useAppSelector(state => state.auth.emailOtp);
  const email = route.params?.email ?? emailOtp.email;

  const [code, setCode] = useState('');
  const [cooldown, setCooldown] = useState(RESEND_COOLDOWN_S);

  useEffect(() => {
    if (cooldown <= 0) {
      return undefined;
    }
    const timer = setTimeout(() => setCooldown(s => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [cooldown]);

  const handleComplete = useCallback(
    async submittedCode => {
      const result = await dispatch(verifyEmailOtp({email, code: submittedCode}));

      if (verifyEmailOtp.fulfilled.match(result)) {
        if (result.payload.status === 'register') {
          navigation.replace('Register', {email: result.payload.email});
        }
        // 'login' needs no navigation call here — RootNavigator reacts to isLoggedIn.
        return;
      }

      if (result.payload?.pendingApproval) {
        navigation.replace('PendingApproval');
        return;
      }

      setCode('');
    },
    [dispatch, email, navigation],
  );

  const handleResend = () => {
    if (cooldown > 0) {
      return;
    }
    setCode('');
    dispatch(sendEmailOtp(email));
    setCooldown(RESEND_COOLDOWN_S);
  };

  const isVerifying = emailOtp.status === 'verifying';

  return (
    <ScreenContainer edges={['top', 'bottom']} glow>
      {navigation.canGoBack() && (
        <TouchableOpacity
          onPress={() => navigation.goBack()}
          hitSlop={10}
          style={{alignSelf: 'flex-start', marginTop: spacing.xs, marginBottom: spacing.xs}}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
      )}

      <View style={{marginTop: spacing.xl}}>
        <AuthHeader
          icon="shield-checkmark-outline"
          eyebrow="VERIFY YOUR EMAIL"
          title="Enter the code"
          subtitle={`We sent a 4-digit code to ${email}.`}
        />

        <View style={{alignItems: 'center'}}>
          <OtpInput
            length={CODE_LENGTH}
            value={code}
            onChangeText={setCode}
            onComplete={handleComplete}
            editable={!isVerifying}
            error={emailOtp.status === 'error' ? emailOtp.error : undefined}
          />

          {isVerifying && (
            <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
              <ActivityIndicator size="small" color={colors.primary} />
              <AppText
                variant="caption"
                color={colors.textSecondary}
                style={{marginLeft: spacing.xs}}>
                Verifying…
              </AppText>
            </View>
          )}
          {emailOtp.status === 'error' && (
            <AppText variant="caption" color={colors.danger} align="center" style={{marginTop: spacing.sm}}>
              {emailOtp.error}
            </AppText>
          )}
        </View>

        <View style={{flexDirection: 'row', justifyContent: 'center', marginTop: spacing.xl}}>
          <AppText variant="body" color={colors.textSecondary}>
            Didn't get a code?{' '}
          </AppText>
          <TouchableOpacity onPress={handleResend} disabled={cooldown > 0} hitSlop={8}>
            <AppText variant="bodyMedium" color={cooldown > 0 ? colors.textMuted : colors.primary}>
              {cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend'}
            </AppText>
          </TouchableOpacity>
        </View>
      </View>
    </ScreenContainer>
  );
};

export default EmailOtpVerifyScreen;
