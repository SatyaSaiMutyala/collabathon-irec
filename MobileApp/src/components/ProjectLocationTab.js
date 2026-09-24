import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import EmptyState from './EmptyState';
import ProjectMapPreview from './ProjectMapPreview';

const CONNECTIVITY_RULES = [
  {test: /metro|station|\btrain\b/i, icon: 'train-outline'},
  {test: /airport/i, icon: 'airplane-outline'},
  {test: /highway|orr|ring road|expressway/i, icon: 'car-outline'},
];

// Solid, not outline — the tile itself is already the "framing", so a filled glyph
// reads clearly at this size the way the reference's tiles do; the outline weight used
// everywhere else in this app looked thin and washed-out against a coloured tile.
const NEARBY_RULES = [
  {test: /school|academy|college|university/i, icon: 'school', tone: 'primary'},
  {test: /hospital|clinic|medi/i, icon: 'medkit', tone: 'danger'},
  {test: /mall|shop|market|store/i, icon: 'bag-handle', tone: 'cyan'},
  {test: /park\b|garden/i, icon: 'leaf', tone: 'success'},
];

/**
 * Admin data is one free-text line per entry ("Metro 800m", "Airport 22km" — see
 * PropertyDetail's stored values), not a {label,value} pair. This peels the trailing
 * distance/time token off so it can sit right-aligned like a value instead of running
 * into the name with no separator; anything that doesn't match that shape is shown
 * whole, undamaged, as the name with no distance column.
 */
function splitDistance(text) {
  const match = /^(.*?)\s*[-–—]?\s*([\d.]+\s?(?:km|m|min(?:ute)?s?))$/i.exec(String(text).trim());
  return match ? {name: match[1].trim(), distance: match[2]} : {name: String(text).trim(), distance: null};
}

function iconFor(rules, text, fallback) {
  return rules.find(rule => rule.test.test(text))?.icon ?? fallback;
}

const ConnectivityRow = ({text, isLast}) => {
  const {colors, spacing} = useAppTheme();
  const {name, distance} = splitDistance(text);

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.sm,
        borderBottomWidth: isLast ? 0 : 1,
        borderBottomColor: colors.border,
      }}>
      <Icon name={iconFor(CONNECTIVITY_RULES, text, 'location-outline')} size={moderateScale(18)} color={colors.textSecondary} />
      <AppText variant="body" color={colors.textSecondary} style={{marginLeft: spacing.sm, flex: 1}}>
        {name}
      </AppText>
      {!!distance && (
        <AppText variant="bodyMedium" weight="semiBold">
          {distance}
        </AppText>
      )}
    </View>
  );
};

const NearbyTile = ({text}) => {
  const {colors, radius, spacing} = useAppTheme();
  const rule = NEARBY_RULES.find(r => r.test.test(text));
  const toneMap = {
    primary: {bg: colors.primarySoft, fg: colors.primaryDark},
    danger: {bg: colors.dangerSoft, fg: colors.danger},
    success: {bg: colors.successSoft, fg: colors.success},
    cyan: {bg: colors.accentCyanSoft, fg: colors.accentCyan},
  };
  // Anything that matched no rule at all (a category not covered above) still needs a
  // tile, not a crash — same cyan the shopping tile itself uses is a reasonable neutral.
  const {bg, fg} = toneMap[rule?.tone] ?? toneMap.cyan;

  return (
    <View style={{width: '33.33%', paddingHorizontal: spacing.xs, marginBottom: spacing.md}}>
      <View
        style={{
          width: moderateScale(56),
          height: moderateScale(56),
          borderRadius: radius.md,
          backgroundColor: bg,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <Icon name={rule?.icon ?? 'location-outline'} size={moderateScale(22)} color={fg} />
      </View>
      <AppText variant="caption" color={colors.textSecondary} numberOfLines={2} style={{marginTop: moderateScale(4)}}>
        {text}
      </AppText>
    </View>
  );
};

/** Location: map, full address, indicative connectivity, and what's nearby. */
const ProjectLocationTab = ({project}) => {
  const {colors, spacing, radius} = useAppTheme();
  const location = project.details?.location ?? {};
  const address =
    location.fullAddress ||
    [location.locality, location.city, location.state, location.pincode].filter(Boolean).join(', ') ||
    null;
  const hasMap = location.latitude != null && location.longitude != null;
  const hasAnything = hasMap || !!address || !!location.connectivityItems?.length || !!location.nearbyItems?.length;

  if (!hasAnything) {
    return (
      <EmptyState
        icon="location-outline"
        title="No location details yet"
        message="Coordinates, address, connectivity and nearby places will show here once the developer adds them."
        style={{marginTop: spacing.xxxl}}
      />
    );
  }

  return (
    <View style={{paddingHorizontal: spacing.lg, paddingTop: spacing.xs}}>
      {hasMap && (
        <ProjectMapPreview
          latitude={location.latitude}
          longitude={location.longitude}
          mapsLink={location.mapsLink}
        />
      )}

      {/* marginTop only when the map actually rendered above it — with no map this is
          the first thing on the tab, and a top margin sized for "gap below the map"
          read as a mysteriously blank strip when there was no map to be below. */}
      {!!address && (
        <View
          style={{
            flexDirection: 'row',
            alignItems: 'flex-start',
            marginTop: hasMap ? spacing.md : 0,
          }}>
          <Icon name="location" size={moderateScale(16)} color={colors.primary} style={{marginTop: moderateScale(2)}} />
          <AppText variant="body" color={colors.textSecondary} style={{marginLeft: spacing.sm, flex: 1}}>
            {address}
          </AppText>
        </View>
      )}

      {!!location.connectivityItems?.length && (
        <View style={{marginTop: spacing.xl}}>
          <View style={{flexDirection: 'row', alignItems: 'baseline'}}>
            <AppText variant="h3">Connectivity</AppText>
            <AppText variant="caption" color={colors.textMuted} style={{marginLeft: spacing.xxs}}>
              (Indicative)
            </AppText>
          </View>
          <View
            style={{
              marginTop: spacing.sm,
              backgroundColor: colors.surface,
              borderRadius: radius.lg,
              paddingHorizontal: spacing.md,
            }}>
            {location.connectivityItems.map((item, index) => (
              <ConnectivityRow key={item} text={item} isLast={index === location.connectivityItems.length - 1} />
            ))}
          </View>
        </View>
      )}

      {!!location.nearbyItems?.length && (
        <View style={{marginTop: spacing.xl}}>
          <AppText variant="h3" style={{marginBottom: spacing.sm}}>
            Nearby
          </AppText>
          <View style={{flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -spacing.xs}}>
            {location.nearbyItems.map(item => (
              <NearbyTile key={item} text={item} />
            ))}
          </View>
        </View>
      )}
    </View>
  );
};

export default ProjectLocationTab;
