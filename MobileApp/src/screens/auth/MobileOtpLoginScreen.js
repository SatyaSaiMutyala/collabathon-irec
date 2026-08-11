import React, {useCallback, useEffect, useRef, useState} from 'react';
import {TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, AuthHeader, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {resetOtp, sendOtp} from '../../store/slices/authSlice';

const MOBILE_LENGTH = 10;
const DEBOUNCE_MS = 500;

/**
 * Channel-partner sign-in, step 1 of 2 — mobile number, then a code.
 *
 * Sends two ways, same underlying call: reaching 10 digits starts a 500ms debounce
 * (not an immediate send on the 10th keystroke — fixing a typo by backspacing through
 * the last digit and retyping it passes through "10 digits present" on every one of
 * those intermediate keystrokes) and sends automatically; the button below does the
 * same thing on demand. The button isn't just a fallback for the impatient — a form
 * with nothing to press reads as unfinished even when the field alone works, so it's
 * also what gives this screen an actual answer to "what do I do here".
 */
const MobileOtpLoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const otp = useAppSelector(state => state.auth.otp);

  const [mobile, setMobile] = useState('');
  const debounceRef = useRef(null);
  // The last number a send actually went out for — guards against firing a second
  // request for the same number from an unrelated re-render.
  const sentForRef = useRef(null);

  useEffect(() => {
    dispatch(resetOtp());
    return () => clearTimeout(debounceRef.current);
  }, [dispatch]);

  const triggerSend = useCallback(
    async candidate => {
      if (candidate.length !== MOBILE_LENGTH || sentForRef.current === candidate) {
        return;
      }
      sentForRef.current = candidate;
      const result = await dispatch(sendOtp(candidate));

      if (sendOtp.fulfilled.match(result)) {
        navigation.navigate('OtpVerify', {mobile: candidate});
      }
    },
    [dispatch, navigation],
  );

  useEffect(() => {
    clearTimeout(debounceRef.current);
    if (mobile.length !== MOBILE_LENGTH) {
      return undefined;
    }
    debounceRef.current = setTimeout(() => triggerSend(mobile), DEBOUNCE_MS);
    return () => clearTimeout(debounceRef.current);
  }, [mobile, triggerSend]);

  const handleChangeText = text => {
    const digits = text.replace(/[^0-9]/g, '').slice(0, MOBILE_LENGTH);
    // Editing after a send invalidates that attempt (the app returned here via the
    // back button, or the number the server rejected is being corrected) — the next
    // completed 10 digits should send again, even if they land back on the same
    // number, rather than the guard above silently doing nothing.
    if (digits !== mobile) {
      sentForRef.current = null;
    }
    setMobile(digits);
  };

  const isSending = otp.status === 'sending';

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

      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        enableResetScrollToCoords={false}
        keyboardShouldPersistTaps="handled">
        <View style={{marginTop: spacing.xl, marginBottom: spacing.xxl}}>
          <AuthHeader
            icon="call-outline"
            eyebrow="CHANNEL PARTNER SIGN-IN"
            title="Enter mobile number"
            subtitle="A private network for HRA's premium developers and partners."
          />
        </View>

        <Input
          label="Mobile Number"
          placeholder="10-digit mobile number"
          leftIcon="call-outline"
          keyboardType="phone-pad"
          maxLength={MOBILE_LENGTH}
          autoFocus
          editable={!isSending}
          value={mobile}
          onChangeText={handleChangeText}
          error={otp.status === 'error' ? otp.error : undefined}
        />

        <Button
          label={isSending ? 'Sending code…' : 'Continue'}
          onPress={() => triggerSend(mobile)}
          disabled={mobile.length !== MOBILE_LENGTH || isSending}
          loading={isSending}
          icon={isSending ? undefined : 'arrow-forward'}
          iconPosition="right"
          style={{
            marginTop: spacing.sm,
            shadowColor: '#000',
            shadowOffset: {width: 0, height: 2},
            shadowOpacity: 0.15,
            shadowRadius: 6,
            elevation: 4,
          }}
        />

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

export default MobileOtpLoginScreen;
