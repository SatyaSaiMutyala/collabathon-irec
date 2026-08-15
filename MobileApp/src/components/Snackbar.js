import React, {useEffect, useRef} from 'react';
import {Animated, Easing, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import {useAppTheme} from '../theme';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {hideSnackbar, selectSnackbar} from '../store/slices/uiSlice';
import AppText from './AppText';

const VISIBLE_MS = 3200;

/**
 * The app's one snackbar. Mounted once in App, driven from the store, so anything —
 * a screen, a thunk, an interceptor — raises one with `dispatch(showSnackbar(…))` and
 * nothing has to thread a callback down to it.
 *
 * Always black. Tone changes only the leading icon, never the surface: a bar that turns
 * red for errors and green for success competes with the badges and status colours the
 * rest of the app already uses for exactly that, and at the bottom of the screen it
 * reads as a second, louder alert system.
 */
const TONE_ICON = {
  neutral: null,
  success: 'checkmark-circle',
  danger: 'alert-circle',
};

const Snackbar = () => {
  const {colors, spacing, roundedRadius} = useAppTheme();
  const insets = useSafeAreaInsets();
  const dispatch = useAppDispatch();
  const snackbar = useAppSelector(selectSnackbar);

  const translateY = useRef(new Animated.Value(100)).current;
  const opacity = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (!snackbar) {
      return undefined;
    }

    Animated.parallel([
      Animated.timing(translateY, {
        toValue: 0,
        duration: 220,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(opacity, {
        toValue: 1,
        duration: 180,
        useNativeDriver: true,
      }),
    ]).start();

    const timer = setTimeout(() => dispatch(hideSnackbar()), VISIBLE_MS);

    // Keyed on `token`, not on the message, so raising the same text twice restarts
    // the timer instead of letting the first one dismiss the second.
    return () => clearTimeout(timer);
  }, [snackbar?.token, dispatch, translateY, opacity, snackbar]);

  useEffect(() => {
    if (!snackbar) {
      translateY.setValue(100);
      opacity.setValue(0);
    }
  }, [snackbar, translateY, opacity]);

  if (!snackbar?.message) {
    return null;
  }

  const icon = TONE_ICON[snackbar.tone] ?? null;

  return (
    <Animated.View
      pointerEvents="box-none"
      style={[
        styles.wrap,
        {
          bottom: insets.bottom + moderateScale(16),
          paddingHorizontal: spacing.lg,
          opacity,
          transform: [{translateY}],
        },
      ]}>
      <View
        // Nothing in the bar is interactive now, so it must not sit on top of whatever
        // it covers for 3.2s swallowing taps meant for the screen underneath.
        pointerEvents="none"
        style={[
          styles.bar,
          {
            backgroundColor: colors.textPrimary,
            borderRadius: roundedRadius.snackbar,
          },
        ]}>
        {!!icon && (
          <Icon
            name={icon}
            size={moderateScale(17)}
            color={colors.textInverse}
            style={{marginRight: moderateScale(8)}}
          />
        )}
        {/* No dismiss button: the bar clears itself after VISIBLE_MS, and a close
            control on something that leaves on its own is an affordance for an action
            the user never needs to take. */}
        <AppText variant="caption" color={colors.textInverse} style={{flex: 1}}>
          {snackbar.message}
        </AppText>
      </View>
    </Animated.View>
  );
};

const styles = {
  wrap: {
    position: 'absolute',
    left: 0,
    right: 0,
  },
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: moderateScale(11),
    paddingHorizontal: moderateScale(14),
  },
};

export default Snackbar;
