import React from 'react';
import {StyleSheet, View} from 'react-native';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * `align` maps to alignSelf. The default keeps a badge hugging its content at the start
 * of the line, which is what 12 of the 13 call sites want — but alignSelf on a child
 * beats alignItems on its parent, so a centred container could not centre a badge no
 * matter what it set. Passing align="center" is how a caller opts out.
 */
const Badge = ({label, tone = 'neutral', align = 'flex-start'}) => {
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
        {backgroundColor: t.bg, borderRadius: roundedRadius.badge, alignSelf: align},
      ]}>
      <AppText variant="overline" color={t.text}>
        {label}
      </AppText>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: moderateScale(10),
    paddingVertical: moderateScale(5),
  },
});

export default Badge;
