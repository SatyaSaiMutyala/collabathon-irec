import {moderateScale, scale, verticalScale} from 'react-native-size-matters';

export {scale, verticalScale, moderateScale};

export const spacing = {
  xxs: scale(4),
  xs: scale(8),
  sm: scale(12),
  md: scale(16),
  lg: scale(20),
  xl: scale(24),
  xxl: scale(32),
  xxxl: scale(40),
};

export const radius = {
  sm: moderateScale(8),
  md: moderateScale(14),
  lg: moderateScale(20),
  xl: moderateScale(28),
  pill: moderateScale(999),
};

export const iconSize = {
  sm: moderateScale(16),
  md: moderateScale(20),
  lg: moderateScale(24),
  xl: moderateScale(32),
};

export const avatarSize = {
  sm: moderateScale(32),
  md: moderateScale(44),
  lg: moderateScale(64),
  xl: moderateScale(88),
};
