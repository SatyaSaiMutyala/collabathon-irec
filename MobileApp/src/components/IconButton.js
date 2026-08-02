import React from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {roundedRadius, useAppTheme} from '../theme';
import AppText from './AppText';

const IconButton = ({
  icon,
  onPress,
  size = 36,
  variant = 'filled',
  badgeCount,
  color,
  backgroundColor,
}) => {
  const {colors} = useAppTheme();
  const dim = moderateScale(size);

  // The glyph scales with the disc instead of being a fixed 19. At the old 44 the
  // fixed size was fine; once the disc came down it left the icon looking stranded
  // in the middle of a black circle. 0.6 keeps a visible ring of fill around it.
  const iconDim = moderateScale(Math.round(size * 0.6));

  const bg =
    backgroundColor ??
    (variant === 'filled' ? colors.primary : variant === 'outline' ? 'transparent' : colors.surface);
  const iconColor = color ?? (variant === 'filled' ? colors.textInverse : colors.textPrimary);

  /**
   * The badge hangs off the ICON's top-right corner, not the button's.
   *
   * Anchoring it to the container put it out on the corner of the disc with a gap of
   * background between it and the bell, so it read as a separate floating dot. These
   * offsets are derived from the icon box, so the badge stays welded to the glyph at
   * any `size` rather than drifting as the disc changes.
   */
  const badgeDim = moderateScale(15);

  /**
   * Placed against the disc's CIRCLE, not its bounding box. Insetting by the square's
   * corner leaves the badge hanging over the edge, because a circle curves away fastest
   * exactly where the badge sits — which is what made it read as floating on the
   * background instead of riding the glyph.
   *
   * Sit the badge's centre on the 45° diagonal at radius (R - badge/2): the furthest out
   * it can go while still being fully inscribed, so it kisses the disc edge and overlaps
   * the icon's top-right corner.
   */
  const radius = dim / 2;
  const centreDistance = Math.max(radius - badgeDim / 2, 0);
  const badgeOffset = radius - centreDistance / Math.SQRT2 - badgeDim / 2;

  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      style={[
        styles.base,
        {
          width: dim,
          height: dim,
          borderRadius: roundedRadius.control,
          backgroundColor: bg,
          borderWidth: variant === 'outline' ? 1.5 : 0,
          borderColor: colors.border,
        },
      ]}>
      <Icon name={icon} size={iconDim} color={iconColor} />
      {!!badgeCount && (
        <View
          style={[
            styles.badge,
            {
              top: badgeOffset,
              right: badgeOffset,
              minWidth: badgeDim,
              height: badgeDim,
              backgroundColor: colors.danger,
              // The ring is the button's own fill, not the page background: the badge
              // now sits over the disc, so it has to cut out of that, not out of white.
              borderColor: bg === 'transparent' ? colors.background : bg,
            },
          ]}>
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
  // Size and offsets are computed per instance from the icon box — see IconButton.
  badge: {
    position: 'absolute',
    borderRadius: roundedRadius.badge,
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
