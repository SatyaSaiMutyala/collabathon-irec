import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * The centered icon-disc + heading treatment for a short, single-purpose auth "gate"
 * screen (mobile number, OTP, developer sign-in) — the same shape
 * PendingApprovalScreen already used for its own full-screen status message, pulled
 * out once these screens needed it too rather than each improvising its own
 * left-aligned text stack.
 */
const AuthHeader = ({icon, eyebrow, title, subtitle}) => {
  const {colors, spacing, roundedRadius, withAlpha} = useAppTheme();

  return (
    <View style={{alignItems: 'center', marginBottom: spacing.xl}}>
      {/* A soft ring behind the disc, not just the flat disc alone — the same
          "glow behind the mark" idea WelcomeScreen uses, scaled down to one faint
          layer so a short gate screen has some depth instead of an icon floating
          on bare white. */}
      <View
        style={{
          width: moderateScale(104),
          height: moderateScale(104),
          borderRadius: roundedRadius.statusIcon,
          backgroundColor: withAlpha(colors.primary, 0.06),
          alignItems: 'center',
          justifyContent: 'center',
          marginBottom: spacing.md,
        }}>
        <View
          style={{
            width: moderateScale(72),
            height: moderateScale(72),
            borderRadius: roundedRadius.statusIcon,
            backgroundColor: colors.primarySoft,
            alignItems: 'center',
            justifyContent: 'center',
          }}>
          <Icon name={icon} size={moderateScale(30)} color={colors.primaryDark} />
        </View>
      </View>

      <AppText variant="overline" color={colors.primary} align="center">
        {eyebrow}
      </AppText>
      <AppText variant="display" align="center" style={{marginTop: moderateScale(4)}}>
        {title}
      </AppText>
      {!!subtitle && (
        <AppText
          variant="body"
          color={colors.textSecondary}
          align="center"
          style={{marginTop: spacing.xs, paddingHorizontal: spacing.sm}}>
          {subtitle}
        </AppText>
      )}
    </View>
  );
};

export default AuthHeader;
