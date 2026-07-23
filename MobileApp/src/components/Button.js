import React from 'react';
import {ActivityIndicator, StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, verticalScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const Button = ({
  label,
  onPress,
  variant = 'primary',
  size = 'lg',
  loading = false,
  disabled = false,
  fullWidth = true,
  icon,
  iconPosition = 'left',
  style,
}) => {
  const {colors, radius, spacing} = useAppTheme();
  const isDisabled = disabled || loading;

  const variantStyles = {
    primary: {bg: colors.primary, text: colors.textInverse},
    secondary: {bg: colors.textPrimary, text: colors.textInverse},
    outline: {bg: 'transparent', border: colors.primary, text: colors.primary},
    ghost: {bg: 'transparent', text: colors.primary},
    danger: {bg: colors.danger, text: colors.textInverse},
  };

  const vs = variantStyles[variant];
  const height = verticalScale(size === 'lg' ? 44 : 38);

  return (
    <TouchableOpacity
      activeOpacity={0.85}
      disabled={isDisabled}
      onPress={onPress}
      style={[
        styles.base,
        {
          height,
          backgroundColor: vs.bg,
          borderWidth: vs.border ? 1.5 : 0,
          borderColor: vs.border,
          borderRadius: radius.sm,
          paddingHorizontal: spacing.md,
          opacity: isDisabled ? 0.55 : 1,
          alignSelf: fullWidth ? 'stretch' : 'flex-start',
        },
        style,
      ]}>
      {loading ? (
        <ActivityIndicator color={vs.text} />
      ) : (
        <View style={styles.content}>
          {icon && iconPosition === 'left' && (
            <Icon name={icon} size={moderateScale(16)} color={vs.text} style={styles.iconLeft} />
          )}
          <AppText variant="button" color={vs.text}>
            {label}
          </AppText>
          {icon && iconPosition === 'right' && (
            <Icon name={icon} size={moderateScale(16)} color={vs.text} style={styles.iconRight} />
          )}
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
  content: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  iconLeft: {
    marginRight: moderateScale(8),
  },
  iconRight: {
    marginLeft: moderateScale(8),
  },
});

export default Button;
