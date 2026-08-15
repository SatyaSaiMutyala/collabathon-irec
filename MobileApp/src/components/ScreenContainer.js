import React from 'react';
import {ScrollView, StatusBar, StyleSheet, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import LinearGradient from 'react-native-linear-gradient';
import {useContentColumn} from '../theme/scaling';
import {useAppTheme} from '../theme';

const ScreenContainer = ({
  edges = ['top'],
  statusBarStyle = 'dark-content',
  backgroundColor,
  // Opt-in only — a single, very restrained wash of the theme's primary colour
  // pinned to the top corner, for the handful of short "gate" screens (sign-in,
  // OTP) that would otherwise be a near-empty white rectangle. Every other screen
  // in the app renders exactly as before; this changes nothing unless asked for.
  glow = false,
  // Opt-in only, and only for screens whose children are plain content. A screen
  // laying out its own FlatList/ScrollView already scrolls and must not be nested
  // inside a second one. Worth having because the body below is `flex: 1`, which
  // pins it to the viewport and silently clips anything taller — the exact defect
  // App Review rejected 1.0 (3) for on the welcome screen. Sizes here run through
  // `moderateScale`, so a layout that fits a phone can still overflow a tablet,
  // where the multiplier is ~1.67x.
  scroll = false,
  style,
  children,
  ...rest
}) => {
  const {colors, spacing, withAlpha} = useAppTheme();

  // Every screen in this app was laid out for one phone-width column. Left to fill a
  // tablet, the same rows become very wide bands carrying a line or two of text with
  // a hand's width of blank on either side — legible in the sense that nothing is cut
  // off, unreadable in the sense that the eye has to travel the whole width to pair a
  // label with its value. Capping the column and centring it is the standard treatment
  // and needs no per-screen redesign.
  const capped = useContentColumn();

  // flexGrow, not flex: the container has to be free to exceed the viewport, while
  // still filling it when the content is shorter so `style` overrides like
  // `justifyContent: 'center'` keep landing where they did before.
  const bodyProps = scroll
    ? {
        style: [styles.base, capped],
        contentContainerStyle: [
          {flexGrow: 1, paddingHorizontal: spacing.lg},
          style,
        ],
      }
    : {style: [{flex: 1, paddingHorizontal: spacing.lg}, capped, style]};
  const Body = scroll ? ScrollView : View;

  return (
    <SafeAreaView
      edges={edges}
      style={[
        styles.base,
        {backgroundColor: backgroundColor ?? colors.background},
      ]}>
      <StatusBar
        barStyle={statusBarStyle}
        backgroundColor={backgroundColor ?? colors.background}
        translucent={false}
      />
      {glow && (
        <LinearGradient
          pointerEvents="none"
          colors={[withAlpha(colors.primary, 0.1), withAlpha(colors.primary, 0)]}
          start={{x: 0.5, y: 0}}
          end={{x: 0.5, y: 0.55}}
          style={StyleSheet.absoluteFillObject}
        />
      )}
      <Body {...rest} {...bodyProps}>
        {children}
      </Body>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  base: {
    flex: 1,
  },
});

export default ScreenContainer;
