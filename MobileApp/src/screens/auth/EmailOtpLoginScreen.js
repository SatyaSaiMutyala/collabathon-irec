import React, {useEffect, useState} from 'react';
import {TouchableOpacity, View} from 'react-native';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {AppText, AuthHeader, Button, Input, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {resetEmailOtp, sendEmailOtp} from '../../store/slices/authSlice';
import {suggestEmailDomain} from '../../utils/email';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Channel-partner sign-in, step 1 of 2 — email, then a 4-digit code. Same shape as
 * MobileOtpLoginScreen, just keyed on email instead of a mobile number (see
 * AuthController::sendEmailOtp) — no debounced auto-send here, since a half-typed
 * email can look "valid enough" at several points before the real address is done.
 */
const EmailOtpLoginScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const emailOtp = useAppSelector(state => state.auth.emailOtp);

  const [email, setEmail] = useState('');

  useEffect(() => {
    dispatch(resetEmailOtp());
  }, [dispatch]);

  const trimmedEmail = email.trim();
  const isFormatValid = EMAIL_PATTERN.test(trimmedEmail);
  // Format alone lets "gmail.como" straight through — it's a syntactically valid
  // domain, just not the one almost anyone actually meant to type. This catches a
  // typo of a well-known provider before it ever reaches a real request, rather
  // than "the OTP never arrived" being the first sign anything was wrong.
  const domainSuggestion = isFormatValid ? suggestEmailDomain(trimmedEmail) : null;
  const isValid = isFormatValid && !domainSuggestion;
  const isSending = emailOtp.status === 'sending';
  const domainSuggestionMessage = domainSuggestion
    ? `Did you mean ${trimmedEmail.slice(0, trimmedEmail.lastIndexOf('@'))}@${domainSuggestion}?`
    : null;

  const handleSubmit = async () => {
    if (!isValid || isSending) {
      return;
    }
    const candidate = email.trim();
    const result = await dispatch(sendEmailOtp(candidate));

    if (sendEmailOtp.fulfilled.match(result)) {
      navigation.navigate('EmailOtpVerify', {email: candidate});
    }
  };

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
            icon="mail-outline"
            eyebrow="CHANNEL PARTNER SIGN-IN"
            title="Enter your email"
            subtitle="A private network for HRA's premium developers and partners."
          />
        </View>

        <Input
          label="Email"
          placeholder="you@company.com"
          leftIcon="mail-outline"
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          autoFocus
          editable={!isSending}
          value={email}
          onChangeText={setEmail}
          onSubmitEditing={handleSubmit}
          error={domainSuggestionMessage ?? (emailOtp.status === 'error' ? emailOtp.error : undefined)}
        />

        <Button
          label={isSending ? 'Sending code…' : 'Continue'}
          onPress={handleSubmit}
          disabled={!isValid || isSending}
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

        {/* Nested `Text`, not a second touchable over the line — same reasoning as
            Checkbox's linkLabel. */}
        <AppText
          variant="caption"
          color={colors.textMuted}
          style={{marginTop: spacing.md, textAlign: 'center'}}>
          By continuing, you agree to HRA's Partner Network{' '}
          <AppText variant="caption" color={colors.primary} onPress={() => navigation.navigate('Terms')}>
            terms
          </AppText>
          .
        </AppText>
      </KeyboardAwareScrollView>
    </ScreenContainer>
  );
};

export default EmailOtpLoginScreen;
