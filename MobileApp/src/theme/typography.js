import {moderateScale} from 'react-native-size-matters';

export const fontFamily = {
  light: 'Outfit-Light',
  regular: 'Outfit-Regular',
  medium: 'Outfit-Medium',
  semiBold: 'Outfit-SemiBold',
  bold: 'Outfit-Bold',
  extraBold: 'Outfit-ExtraBold',
};

export const textVariants = {
  display: {
    fontFamily: fontFamily.extraBold,
    fontSize: moderateScale(30),
    lineHeight: moderateScale(36),
    letterSpacing: -0.4,
  },
  h1: {
    fontFamily: fontFamily.bold,
    fontSize: moderateScale(24),
    lineHeight: moderateScale(30),
    letterSpacing: -0.2,
  },
  h2: {
    fontFamily: fontFamily.bold,
    fontSize: moderateScale(20),
    lineHeight: moderateScale(26),
  },
  h3: {
    fontFamily: fontFamily.semiBold,
    fontSize: moderateScale(17),
    lineHeight: moderateScale(22),
  },
  subtitle: {
    fontFamily: fontFamily.medium,
    fontSize: moderateScale(15),
    lineHeight: moderateScale(20),
  },
  body: {
    fontFamily: fontFamily.regular,
    fontSize: moderateScale(14.5),
    lineHeight: moderateScale(21),
  },
  bodyMedium: {
    fontFamily: fontFamily.medium,
    fontSize: moderateScale(14.5),
    lineHeight: moderateScale(21),
  },
  caption: {
    fontFamily: fontFamily.regular,
    fontSize: moderateScale(12.5),
    lineHeight: moderateScale(17),
  },
  captionMedium: {
    fontFamily: fontFamily.medium,
    fontSize: moderateScale(12.5),
    lineHeight: moderateScale(17),
  },
  overline: {
    fontFamily: fontFamily.semiBold,
    fontSize: moderateScale(11),
    lineHeight: moderateScale(14),
    letterSpacing: 0.6,
  },
  button: {
    fontFamily: fontFamily.semiBold,
    fontSize: moderateScale(14),
    lineHeight: moderateScale(18),
  },
};
