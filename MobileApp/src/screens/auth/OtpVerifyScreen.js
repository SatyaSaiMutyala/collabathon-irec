import React, {useCallback, useEffect, useRef, useState} from 'react';
import {ActivityIndicator, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, AuthHeader, OtpInput, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {sendOtp, verifyOtp} from '../../store/slices/authSlice';

const RESEND_COOLDOWN_S = 30;

/**
 * Channel-partner sign-in, step 2 of 2. No "Verify" button: the 6th digit auto-submits
 * (OtpInput's `onComplete`). What happens next is entirely the server's own fork (see
 * `verifyOtp`'s thunk and `AuthController::verifyOtp`) —
 *
 *   'login'    — RootNavigator swaps to the broker stack the instant `isLoggedIn`
 *                flips in the store; this screen calls no navigation itself, it just
 *                stops being shown.
 *   'register' — no account for this number yet; on to CompleteProfile, carrying the
 *                one-time verification_token that screen submits instead of a password.
 *   403        — an account exists but isn't approved yet (or was rejected); on to
 *                PendingApproval, which reads the status the thunk already wrote.
 *   otherwise  — wrong code: shown inline, boxes clear, ready to retry immediately.
 *
 * Auto-fill: `otp.debugCode` is non-null only when the API chose to echo the code
 * back — off-production always, on production only when OTP_EXPOSE_CODE is set (see
 * AuthController::exposesOtpCode). Nothing here decides that; the server does, per
 * host. When it arrives, the code is filled in and submitted automatically rather
 * than making a tester read it out of a log line. There is no SMS gateway yet, so
 * this is currently the only way the flow completes on a deployed test server.
 */
const OtpVerifyScreen = ({navigation, route}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const otp = useAppSelector(state => state.auth.otp);
  const mobile = route.params?.mobile ?? otp.mobile;

  const [code, setCode] = useState('');
  const [cooldown, setCooldown] = useState(RESEND_COOLDOWN_S);
  // The debugCode value already auto-filled, so a resend's fresh code triggers this
  // again but the same one never re-submits itself in a loop.
  const autoFilledRef = useRef(null);

  useEffect(() => {
    if (cooldown <= 0) {
      return undefined;
    }
    const timer = setTimeout(() => setCooldown(s => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [cooldown]);

  const handleComplete = useCallback(
    async submittedCode => {
      const result = await dispatch(verifyOtp({mobile, code: submittedCode}));

      if (verifyOtp.fulfilled.match(result)) {
        if (result.payload.status === 'register') {
          navigation.replace('CompleteProfile', {
            mobile: result.payload.mobile,
            verificationToken: result.payload.verification_token,
          });
        }
        // 'login' needs no navigation call here — RootNavigator reacts to isLoggedIn.
        return;
      }

      if (result.payload?.pendingApproval) {
        navigation.replace('PendingApproval');
        return;
      }

      // Wrong code — try again with an empty row rather than leaving the rejected
      // digits sitting there implying they might still be right.
      setCode('');
    },
    [dispatch, mobile, navigation],
  );

  // Auto-fill whenever the server chose to send a code back.
  //
  // The gate is the server's alone (OTP_EXPOSE_CODE — see config/app.php). It used to
  // be `__DEV__` as well, which meant a build installed on a tester's phone never
  // auto-filled against the deployed test server: the code came down and was ignored.
  // Since nothing has an SMS gateway yet, that left the flow uncompletable.
  //
  // The consequence is that whether an auth code is ever echoed is decided in one
  // place, on the server, rather than half here and half there. Turn OTP_EXPOSE_CODE
  // off and this effect goes quiet on every build, release or debug.
  useEffect(() => {
    if (!otp.debugCode || otp.debugCode === autoFilledRef.current) {
      return;
    }
    autoFilledRef.current = otp.debugCode;
    setCode(otp.debugCode);
    handleComplete(otp.debugCode);
  }, [otp.debugCode, handleComplete]);

  const handleResend = () => {
    if (cooldown > 0) {
      return;
    }
    setCode('');
    dispatch(sendOtp(mobile));
    setCooldown(RESEND_COOLDOWN_S);
  };

  const isVerifying = otp.status === 'verifying';

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
          eyebrow="VERIFY YOUR NUMBER"
          title="Enter the code"
          subtitle={`We sent a 6-digit code to ${mobile}.`}
        />

        {/* Visible on purpose rather than a silent auto-submit — a tester should be
            able to tell at a glance that the code came from the API's debug field,
            not typed. Never renders outside a debug build (see the effect above). */}
        {__DEV__ && otp.debugCode && (
          <AppText variant="caption" color={colors.warning} align="center" style={{marginTop: -spacing.md}}>
            Dev only: auto-filled test code {otp.debugCode} from the server response.
          </AppText>
        )}

        <View style={{alignItems: 'center'}}>
          <OtpInput
            value={code}
            onChangeText={setCode}
            onComplete={handleComplete}
            editable={!isVerifying}
            error={otp.status === 'error' ? otp.error : undefined}
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
          {otp.status === 'error' && (
            <AppText variant="caption" color={colors.danger} align="center" style={{marginTop: spacing.sm}}>
              {otp.error}
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

export default OtpVerifyScreen;
