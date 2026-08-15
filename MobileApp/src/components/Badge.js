import React from 'react';
import {StyleSheet, View} from 'react-native';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const Badge = ({label, tone = 'neutral'}) => {
  const {colors, roundedRadius} = useAppTheme();

  const toneStyles = {
    primary: {bg: colors.primarySoft, text: colors.primaryDark},
    success: {bg: colors.successSoft, text: colors.success},
    danger: {bg: colors.dangerSoft, text: colors.danger},
    warning: {bg: colors.warningSoft, text: colors.warning},
    neutral: {bg: colors.surface, text: colors.textSecondary},
  };

  const t = toneStyles[tone];

  return (
    <View
      style={[
        styles.container,
        {backgroundColor: t.bg, borderRadius: roundedRadius.badge},
      ]}>
      <AppText variant="overline" color={t.text}>
        {label}
      </AppText>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignSelf: 'flex-start',
    paddingHorizontal: moderateScale(10),
    paddingVertical: moderateScale(5),
  },
});

export default Badge;
