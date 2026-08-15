import {moderateScale, scale, verticalScale} from './scaling';

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

/**
 * Square corners everywhere. The scale is kept as named tokens rather than deleted so
 * every existing `radius.md` call site stays valid — they simply all resolve to 0.
 * Surfaces, cards, inputs and chips all sit flush.
 */
export const radius = {
  sm: 0,
  md: 0,
  lg: 0,
  xl: 0,
  pill: 0,
};

/**
 * The exceptions to the square rule. These read as markers, controls and people floating
 * *on top of* content rather than as surfaces of their own, so squaring them made them
 * merge into whatever sits behind. Kept in a separate map so `radius.*` stays 0 at every
 * call site and the exceptions remain countable — reach for these only when a shape is
 * meant to look detached from the layout, not to round a panel.
 */
export const roundedRadius = {
  badge: moderateScale(999), // status chips + the IconButton unread-count bubble
  notification: moderateScale(999), // notification row icon disc + unread dot
  control: moderateScale(999), // circular icon controls, e.g. the back button
  avatar: moderateScale(999), // profile photos and their initials fallback
  step: moderateScale(999), // numbered section markers (1, 2, 3) in SectionHeader
  statusIcon: moderateScale(999), // the large icon disc on a full-screen status message
  // The only exception that is not a full pill: the snackbar is a wide bar, and pilling
  // it turns the ends into long arcs that read as a lozenge rather than a surface.
  snackbar: moderateScale(10),
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
