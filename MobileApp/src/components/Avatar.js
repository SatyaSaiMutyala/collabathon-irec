import React from 'react';
import {Image, StyleSheet, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {roundedRadius, useAppTheme} from '../theme';
import AppText from './AppText';
import {initialsOf} from '../utils/name';

// A logo is a wide wordmark, not a square headshot — logo mode keeps `dim` as the
// height and widens to a fixed 5:2 ratio instead of reusing the square person-photo
// box, so a properly-cropped logo fills its frame edge-to-edge instead of sitting tiny
// and letterboxed inside a square one.
const LOGO_RATIO = 2.5;

const Avatar = ({uri, name, size = 'md', ringColor, showVerified, shape = 'circle'}) => {
  const {colors, avatarSize} = useAppTheme();
  // A raw number sizes the box directly (in points, before scaling) — an escape hatch
  // for the one caller that needs a height between two named tokens, without adding a
  // new token every other Avatar everywhere else would have to consider too.
  const dim = typeof size === 'number' ? moderateScale(size) : avatarSize[size];
  const width = shape === 'square' ? dim * LOGO_RATIO : dim;
  // A ring reads as an intentional frame on a person photo, but on a logo it shows up
  // as a stray border cutting across the image itself — skip it regardless of whether
  // a caller passes ringColor, same as avatar.blade.php does on the web side.
  const ringWidth = ringColor && shape !== 'square' ? moderateScale(2) : 0;
  const radius = shape === 'square' ? roundedRadius.logo : roundedRadius.avatar;

  return (
    <View
      style={[
        styles.wrapper,
        {
          width: width + ringWidth * 2,
          height: dim + ringWidth * 2,
          borderRadius: radius,
          borderWidth: ringWidth,
          borderColor: ringColor,
        },
      ]}>
      {uri ? (
        shape === 'square' ? (
          // No padding here on purpose: a logo picked through the crop tool is already
          // cropped to this exact 5:2 ratio before it's saved, so `contain` alone fills
          // the box edge to edge. Padding was tried as a hedge for legacy un-cropped
          // logos, but it eats into width and height unevenly at a 5:2 box — the
          // *content* area it leaves behind isn't 5:2 anymore, so `contain` reintroduces
          // a gap even for an image that was already cropped correctly. A background on
          // the wrapper (not the Image) is still the fallback for the rare source that
          // still doesn't match the ratio.
          <View
            style={{
              width,
              height: dim,
              borderRadius: radius,
              backgroundColor: colors.card,
              overflow: 'hidden',
            }}>
            <Image source={{uri}} resizeMode="contain" style={{width: '100%', height: '100%'}} />
          </View>
        ) : (
          <Image
            source={{uri}}
            resizeMode="cover"
            style={{width, height: dim, borderRadius: radius}}
          />
        )
      ) : (
        <View
          style={[
            styles.fallback,
            {
              width,
              height: dim,
              borderRadius: radius,
              backgroundColor: colors.primarySoft,
            },
          ]}>
          {/* Sized off the box itself, not a fixed text variant — a 32pt circle and a
              64pt logo box need visibly different-sized initials, and a flat font
              size left the bigger boxes (the developer card's logo-shaped fallback,
              in particular) looking like the letters had been left behind at the
              smallest size. */}
          <AppText
            variant="bodyMedium"
            weight="semiBold"
            color={colors.primaryDark}
            style={{fontSize: dim * 0.42, lineHeight: dim * 0.5}}>
            {initialsOf(name)}
          </AppText>
        </View>
      )}
      {showVerified && (
        <View style={[styles.badge, {backgroundColor: colors.success, borderColor: colors.card}]}>
          <Icon name="checkmark" size={moderateScale(9)} color={colors.white} />
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    right: -moderateScale(1),
    bottom: -moderateScale(1),
    width: moderateScale(16),
    height: moderateScale(16),
    borderRadius: roundedRadius.badge,
    borderWidth: moderateScale(1.5),
    alignItems: 'center',
    justifyContent: 'center',
  },
});

export default Avatar;
