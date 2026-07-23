import React from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const IconButton = ({
  icon,
  onPress,
  size = 44,
  variant = 'filled',
  badgeCount,
  color,
  backgroundColor,
}) => {
  const {colors, radius} = useAppTheme();
  const dim = moderateScale(size);

  const bg =
    backgroundColor ??
    (variant === 'filled' ? colors.primary : variant === 'outline' ? 'transparent' : colors.surface);
  const iconColor = color ?? (variant === 'filled' ? colors.textInverse : colors.textPrimary);

  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      style={[
        styles.base,
        {
          width: dim,
          height: dim,
          borderRadius: radius.pill,
          backgroundColor: bg,
          borderWidth: variant === 'outline' ? 1.5 : 0,
          borderColor: colors.border,
        },
      ]}>
      <Icon name={icon} size={moderateScale(19)} color={iconColor} />
      {!!badgeCount && (
        <View style={[styles.badge, {backgroundColor: colors.danger, borderColor: colors.background}]}>
          <AppText variant="overline" color={colors.white} style={styles.badgeText}>
            {badgeCount > 9 ? '9+' : badgeCount}
          </AppText>
        </View>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  base: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    top: -moderateScale(2),
    right: -moderateScale(2),
    minWidth: moderateScale(18),
    height: moderateScale(18),
    borderRadius: moderateScale(9),
    borderWidth: moderateScale(1.5),
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: moderateScale(3),
  },
  badgeText: {
    fontSize: moderateScale(9.5),
    lineHeight: moderateScale(12),
  },
});

export default IconButton;
