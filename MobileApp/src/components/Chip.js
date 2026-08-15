import React from 'react';
import {StyleSheet, TouchableOpacity} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const Chip = ({label, active = false, onPress, icon, compact = false, tone = 'primary'}) => {
  const {colors, radius} = useAppTheme();

  const toneColor = {
    primary: colors.primary,
    success: colors.success,
    warning: colors.warning,
    danger: colors.danger,
  }[tone];

  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      style={[
        styles.container,
        compact && styles.containerCompact,
        {
          borderRadius: radius.pill,
          backgroundColor: active ? toneColor : colors.surface,
          borderColor: active ? toneColor : colors.border,
        },
      ]}>
      <AppText
        variant={compact ? 'caption' : 'captionMedium'}
        color={active ? colors.textInverse : colors.textSecondary}
        style={{lineHeight: moderateScale(compact ? 14 : 15)}}>
        {label}
      </AppText>
      {icon && (
        <Icon
          name={icon}
          size={moderateScale(13)}
          color={active ? colors.textInverse : colors.textSecondary}
          style={styles.icon}
        />
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: moderateScale(14),
    paddingVertical: moderateScale(9),
    borderWidth: 1,
    marginRight: moderateScale(8),
  },
  containerCompact: {
    paddingHorizontal: moderateScale(10),
    paddingVertical: moderateScale(5),
    marginRight: moderateScale(6),
  },
  icon: {
    marginLeft: moderateScale(4),
  },
});

export default Chip;
