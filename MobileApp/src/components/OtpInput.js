import React, {useRef} from 'react';
import {Platform, Pressable, StyleSheet, TextInput, View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * A `length`-box OTP field backed by one hidden `TextInput` — one real input for the
 * keyboard/autofill to talk to, `length` plain boxes just showing what's been typed.
 * Deliberately not one `TextInput` per digit: that shape needs manual focus-passing on
 * every keystroke (type → focus next, backspace on empty → focus previous), which is
 * exactly where paste and SMS autofill go wrong — an autofilled or pasted code doesn't
 * arrive one keystroke at a time, so a focus-chained implementation only ever fills
 * the box that happened to be focused. One input sidesteps that entirely.
 *
 * Purely controlled: this component holds no state of its own. `onChangeText` fires on
 * every keystroke, `onComplete` fires once, exactly when the value reaches `length` —
 * screens that want to auto-verify hang the API call off `onComplete`.
 */
const OtpInput = ({
  length = 6,
  value,
  onChangeText,
  onComplete,
  error,
  autoFocus = true,
  editable = true,
}) => {
  const {colors, spacing, radius} = useAppTheme();
  const inputRef = useRef(null);
  const digits = value.split('');

  const handleChangeText = text => {
    const next = text.replace(/[^0-9]/g, '').slice(0, length);
    onChangeText(next);
    if (next.length === length) {
      onComplete?.(next);
    }
  };

  return (
    <Pressable onPress={() => inputRef.current?.focus()} accessibilityRole="none">
      <View style={styles.row}>
        {Array.from({length}).map((_, index) => {
          const filled = digits[index];
          // The box right after the last typed digit reads as "where typing resumes"
          // — the closest a static box row gets to a blinking caret.
          const isCursor = index === digits.length && value.length < length;

          return (
            <View
              key={index}
              style={[
                styles.box,
                {
                  borderColor: error
                    ? colors.danger
                    : isCursor
                    ? colors.primary
                    : colors.border,
                  borderRadius: radius.sm,
                  backgroundColor: colors.background,
                  marginRight: index === length - 1 ? 0 : spacing.xs,
                },
              ]}>
              <AppText variant="h2">{filled ?? ''}</AppText>
            </View>
          );
        })}
      </View>

      {/* Positioned over the boxes rather than off-screen: iOS's SMS-autofill QuickType
          bar only offers to fill a field it considers on-screen and reachable. */}
      <TextInput
        ref={inputRef}
        value={value}
        onChangeText={handleChangeText}
        keyboardType="number-pad"
        textContentType="oneTimeCode"
        autoComplete={Platform.OS === 'android' ? 'sms-otp' : undefined}
        maxLength={length}
        autoFocus={autoFocus}
        editable={editable}
        caretHidden
        style={styles.hiddenInput}
      />
    </Pressable>
  );
};

const BOX = moderateScale(46);

const styles = StyleSheet.create({
  row: {flexDirection: 'row'},
  box: {
    width: BOX,
    height: BOX,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  // Invisible but present and focusable — opacity 0 rather than display:none, which
  // some Android keyboards refuse to open a keyboard for.
  hiddenInput: {
    position: 'absolute',
    top: 0,
    left: 0,
    width: BOX * 6,
    height: BOX,
    opacity: 0,
  },
});

export default OtpInput;
