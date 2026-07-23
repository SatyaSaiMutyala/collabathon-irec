import React from 'react';
import {StatusBar, StyleSheet, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {useAppTheme} from '../theme';

const ScreenContainer = ({
  edges = ['top'],
  statusBarStyle = 'dark-content',
  backgroundColor,
  style,
  children,
  ...rest
}) => {
  const {colors, spacing} = useAppTheme();

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
      />
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
