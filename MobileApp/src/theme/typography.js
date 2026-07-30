import {Platform} from 'react-native';
import {moderateScale} from 'react-native-size-matters';

/**
 * Type system. Requested stack: Helvetica Neue, Helvetica, Arial, sans-serif.
 *
 * React Native's `fontFamily` takes ONE family name — it is not a CSS fallback list —
 * so the stack is resolved per platform here rather than passed through verbatim:
 *
 *   - iOS ships Helvetica Neue, so it is named directly.
 *   - Android ships none of Helvetica Neue / Helvetica / Arial. Leaving the family
 *     unset resolves to the platform's default grotesque (Roboto), which is the
 *     `sans-serif` tail of the requested stack. Naming a missing family would land on
 *     the same face, but silently — being explicit keeps the intent legible.
 *
 * Weight comes from `fontWeight`, not from per-weight family names. The previous
 * Outfit setup shipped six TTFs and selected them by filename; a system face exposes
 * its weights natively, so one family plus a numeric weight covers the whole scale.
 */
const SANS = Platform.select({
  ios: 'Helvetica Neue',
  android: undefined,
  default: 'Helvetica Neue',
});

/**
 * Weight tokens. Keyed by the same names the old family map used, so `weight="medium"`
 * and friends keep working at every call site.
 */
export const fontWeight = {
  light: '300',
  regular: '400',
  medium: '500',
  semiBold: '600',
  bold: '700',
  extraBold: '800',
};

/** Every token resolves to the one family; kept as a map so existing call sites hold. */
export const fontFamily = {
  light: SANS,
  regular: SANS,
  medium: SANS,
  semiBold: SANS,
  bold: SANS,
  extraBold: SANS,
};

/**
 * Sizes are unchanged from the previous scale so existing layouts keep their rhythm.
 * What is retuned is tracking and leading, because Helvetica is a tighter, more neutral
 * grotesque than the geometric face it replaces:
 *
 *   - Display and headings take negative tracking. At large sizes Helvetica's default
 *     spacing reads loose, and slight negative tracking is what makes it look composed.
 *   - Body and caption sit at 0 — Helvetica's text-size metrics need no correction.
 *   - Overline keeps positive tracking; it is the one uppercase style, and uppercase
 *     always needs opening up.
 *
 * Leading is a consistent ratio rather than an ad-hoc number per style: ~1.2 for
 * display and headings, ~1.45 for body copy, ~1.35 for supporting text.
 */
export const textVariants = {
  display: {
    fontFamily: SANS,
    fontWeight: fontWeight.bold,
    fontSize: moderateScale(30),
    lineHeight: moderateScale(36),
    letterSpacing: -0.6,
  },
  h1: {
    fontFamily: SANS,
    fontWeight: fontWeight.bold,
    fontSize: moderateScale(24),
    lineHeight: moderateScale(29),
    letterSpacing: -0.4,
  },
  h2: {
    fontFamily: SANS,
    fontWeight: fontWeight.semiBold,
    fontSize: moderateScale(20),
    lineHeight: moderateScale(25),
    letterSpacing: -0.3,
  },
  h3: {
    fontFamily: SANS,
    fontWeight: fontWeight.semiBold,
    fontSize: moderateScale(17),
    lineHeight: moderateScale(22),
    letterSpacing: -0.2,
  },
  subtitle: {
    fontFamily: SANS,
    fontWeight: fontWeight.medium,
    fontSize: moderateScale(15),
    lineHeight: moderateScale(20),
    letterSpacing: -0.1,
  },
  body: {
    fontFamily: SANS,
    fontWeight: fontWeight.regular,
    fontSize: moderateScale(14.5),
    lineHeight: moderateScale(21),
    letterSpacing: 0,
  },
  bodyMedium: {
    fontFamily: SANS,
    fontWeight: fontWeight.medium,
    fontSize: moderateScale(14.5),
    lineHeight: moderateScale(21),
    letterSpacing: 0,
  },
  caption: {
    fontFamily: SANS,
    fontWeight: fontWeight.regular,
    fontSize: moderateScale(12.5),
    lineHeight: moderateScale(17),
    letterSpacing: 0,
  },
  captionMedium: {
    fontFamily: SANS,
    fontWeight: fontWeight.medium,
    fontSize: moderateScale(12.5),
    lineHeight: moderateScale(17),
    letterSpacing: 0,
  },
  overline: {
    fontFamily: SANS,
    fontWeight: fontWeight.semiBold,
    fontSize: moderateScale(11),
    lineHeight: moderateScale(14),
    letterSpacing: 0.8,
  },
  button: {
    fontFamily: SANS,
    fontWeight: fontWeight.semiBold,
    fontSize: moderateScale(14),
    lineHeight: moderateScale(18),
    letterSpacing: -0.1,
  },
};
