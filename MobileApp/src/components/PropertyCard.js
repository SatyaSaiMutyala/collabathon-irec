import React from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import SwipeableImages from './SwipeableImages';

// A fixed card height, not one derived from the (portrait) crop ratio — SwipeableImages
// renders with resizeMode "contain" (see that file), so a taller source photo still shows
// in full either way; this height only controls how much of a scrolling list this one
// card takes up, independent of the source image's own shape.
const CARD_HEIGHT = moderateScale(240);

// en-IN — matches the max half of the same range, built separately in
// normalizers.js's `priceUnit`. INR pricing reads in lakhs/crores throughout the
// rest of the app (see LeadCard's own money() helper), not Western 3-digit grouping.
function formatPrice(value) {
  return new Intl.NumberFormat('en-IN').format(value);
}

const PropertyCard = ({project, onPress, showDots = true, priceVariant = 'h1'}) => {
  const {colors, radius, roundedRadius, spacing} = useAppTheme();

  return (
    <TouchableOpacity activeOpacity={0.9} onPress={onPress} style={{marginBottom: spacing.md}}>
      <View style={[styles.imageWrap, {borderRadius: radius.lg}]}>
        <SwipeableImages
          images={project.images?.length ? project.images : [project.coverImage]}
          height={CARD_HEIGHT}
          dotsPosition="top"
          showDots={showDots}
        />
        <View pointerEvents="none" style={StyleSheet.absoluteFillObject}>
          <LinearGradient
            colors={['transparent', colors.overlayStrong]}
            style={StyleSheet.absoluteFillObject}
          />

          <View style={[styles.statusPill, {backgroundColor: colors.primary, borderRadius: roundedRadius.badge}]}>
            <AppText variant="captionMedium" color={colors.textInverse}>
              {project.listingType.toUpperCase()}
            </AppText>
          </View>

          <View style={[styles.metaPill, {backgroundColor: colors.overlaySoft, borderRadius: roundedRadius.badge}]}>
            <AppText variant="overline" color={colors.textInverse}>
              {project.postedDaysAgo === 0 ? 'TODAY' : `${project.postedDaysAgo} DAYS AGO`}
            </AppText>
          </View>

          <View style={styles.bottomContent}>
            {/* The project's own currency, not a hard-coded one — every project is
                priced in INR now, and the card was still labelling them AED. */}
            <AppText variant="overline" color={colors.textInverse}>
              {project.currency ?? 'INR'}
            </AppText>
            <View style={styles.priceRow}>
              <AppText variant={priceVariant} color={colors.textInverse}>
                {formatPrice(project.price)}
              </AppText>
              {/* Always "Onwards" here, not `project.priceUnit` — that field can carry
                  a "– <max>" range for legacy two-price projects, but this card only
                  ever shows the starting price, so the suffix must match that. */}
              {!!project.price && (
                <AppText variant="bodyMedium" color={colors.textInverse} style={styles.priceUnit}>
                  {' '}
                  Onwards
                </AppText>
              )}
            </View>
            <AppText variant="bodyMedium" color={colors.textInverse} style={{marginTop: moderateScale(2)}}>
              {project.listingType} · {project.type}
            </AppText>
            {!!project.name && (
              <AppText
                variant="bodyMedium"
                weight="semiBold"
                color={colors.textInverse}
                numberOfLines={1}
                style={{marginTop: moderateScale(2)}}>
                {project.name}
              </AppText>
            )}
            <View style={styles.locationRow}>
              <AppText variant="caption" color={colors.textInverse}>
                📍 {project.location}
              </AppText>
              <View style={[styles.photoBadge, {backgroundColor: colors.overlaySoft, borderRadius: roundedRadius.badge}]}>
                <AppText variant="captionMedium" color={colors.textInverse}>
                  {project.photoCount}
                </AppText>
              </View>
            </View>
          </View>
        </View>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  imageWrap: {
    height: CARD_HEIGHT,
    overflow: 'hidden',
  },
  statusPill: {
    position: 'absolute',
    top: moderateScale(14),
    left: moderateScale(14),
    paddingHorizontal: moderateScale(12),
    paddingVertical: moderateScale(6),
  },
  metaPill: {
    position: 'absolute',
    top: moderateScale(14),
    right: moderateScale(14),
    paddingHorizontal: moderateScale(10),
    paddingVertical: moderateScale(5),
  },
  bottomContent: {
    position: 'absolute',
    left: moderateScale(16),
    right: moderateScale(16),
    bottom: moderateScale(14),
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
  },
  priceUnit: {
    opacity: 0.85,
  },
  locationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: moderateScale(6),
  },
  photoBadge: {
    paddingHorizontal: moderateScale(9),
    paddingVertical: moderateScale(3),
  },
});

export default PropertyCard;
