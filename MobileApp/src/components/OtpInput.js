import React, {useRef} from 'react';
import {Platform, Pressable, StyleSheet, TextInput, View} from 'react-native';
import {moderateScale} from '../theme/scaling';
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

  // Plain `.focus()` is a no-op once the keyboard has been dismissed without the input
  // itself losing focus — e.g. a "hide keyboard" affordance that only resigns the
  // keyboard, not the responder, leaves the input still logically focused, so asking it
  // to focus again does nothing and the keyboard stays down. Forcing a real blur first
  // makes the following focus() a genuine state change, which is what actually brings
  // the keyboard back — but the two calls can't be back-to-back: iOS's own keyboard
  // dismiss animation takes ~250ms, and a focus() issued while it's still mid-flight is
  // silently dropped rather than queued. A single animation frame (~16ms) isn't enough
  // of a gap; this waits out the dismiss first.
  const reopenKeyboard = () => {
    inputRef.current?.blur();
    setTimeout(() => inputRef.current?.focus(), 120);
  };

  return (
    <Pressable
      onPress={reopenKeyboard}
      // A native Android EditText can claim a touch itself — even one landing on a
      // sibling — before RN's own gesture responder ever sees it, which is what made
      // taps on the boxes silently do nothing (confirmed via device logs: no press,
      // no touch, nothing). `box-only` makes the Pressable the sole eligible touch
      // target anywhere in this subtree; every child, hidden input included, is
      // untouchable regardless of its own pointerEvents.
      pointerEvents="box-only"
      accessibilityRole="none">
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
                isCursor ? styles.shadowActive : styles.shadowResting,
                {
                  borderColor: error
                    ? colors.danger
                    : isCursor
                    ? colors.primary
                    : colors.border,
                  // Radius stays 0 — same square-corners rule as every other field.
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
          bar only offers to fill a field it considers on-screen and reachable.
          `pointerEvents="none"` here too, belt-and-braces alongside the Pressable's own
          `box-only` above — every tap on this row is routed through `reopenKeyboard`,
          never this input's own native tap-to-focus, which is a no-op once it's
          already (still) focused and so never actually brings the keyboard back. */}
      <TextInput
        ref={inputRef}
        pointerEvents="none"
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
  // Same resting/active shadow language as Input/Dropdown/DateField — the digit
  // currently being typed into lifts slightly, same as a focused text field.
  shadowResting: {
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 1,
  },
  shadowActive: {
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
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
