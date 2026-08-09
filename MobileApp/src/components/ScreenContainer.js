import React from 'react';
import {StatusBar, StyleSheet, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import LinearGradient from 'react-native-linear-gradient';
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
  style,
  children,
  ...rest
}) => {
  const {colors, spacing, withAlpha} = useAppTheme();

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
      <View
        {...rest}
        style={[{flex: 1, paddingHorizontal: spacing.lg}, style]}>
        {children}
      </View>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  base: {
    flex: 1,
  },
});

export default ScreenContainer;
