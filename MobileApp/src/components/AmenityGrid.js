import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const TILE = moderateScale(56);

/**
 * Amenities are free-text (Settings → Amenities lets an admin add/rename any of them —
 * see `App\Models\Amenity`), so there is no fixed catalogue to key a per-amenity icon
 * off. This is a best-effort keyword match against the current catalogue's actual
 * names, ordered most-specific first; anything unrecognised — a brand-new amenity an
 * admin adds tomorrow — falls through to a plain neutral tile rather than a crash or a
 * blank space.
 */
const RULES = [
  {test: /club/i, icon: 'home', tone: 'primary'},
  {test: /pool/i, icon: 'water', tone: 'cyan'},
  {test: /gym/i, icon: 'barbell', tone: 'success'},
  {test: /garden|landscap/i, icon: 'leaf', tone: 'yellow'},
  {test: /charg/i, icon: 'flash', tone: 'orange'},
  {test: /jog|track|sport/i, icon: 'walk', tone: 'success'},
  {test: /kids|play|child/i, icon: 'happy', tone: 'pink'},
  {test: /secur|cctv/i, icon: 'shield-checkmark', tone: 'danger'},
  {test: /backup/i, icon: 'flash', tone: 'warning'},
  {test: /\blift\b|elevator/i, icon: 'swap-vertical', tone: 'primary'},
  {test: /rain/i, icon: 'rainy', tone: 'cyan'},
  {test: /concierge/i, icon: 'person-circle', tone: 'primary'},
  {test: /park/i, icon: 'car', tone: 'warning'},
];

function styleFor(name, colors) {
  const rule = RULES.find(r => r.test.test(name));
  const toneMap = {
    primary: {bg: colors.primarySoft, fg: colors.primaryDark},
    success: {bg: colors.successSoft, fg: colors.success},
    warning: {bg: colors.warningSoft, fg: colors.warning},
    danger: {bg: colors.dangerSoft, fg: colors.danger},
    cyan: {bg: colors.accentCyanSoft, fg: colors.accentCyan},
    yellow: {bg: colors.accentYellowSoft, fg: colors.accentOrange}, // dark text on a pale yellow tile
    orange: {bg: colors.accentOrangeSoft, fg: colors.accentOrange},
    pink: {bg: colors.accentPinkSoft, fg: colors.accentPink},
  };

  return {
    icon: rule?.icon ?? 'checkmark-circle-outline',
    ...(toneMap[rule?.tone] ?? toneMap.primary),
  };
}

/** A wrapping grid of icon tiles, one per amenity name the project actually lists. */
const AmenityGrid = ({amenities = []}) => {
  const {colors, radius, spacing} = useAppTheme();

  if (!amenities.length) {
    return null;
  }

  return (
    <View style={{flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -spacing.xs}}>
      {amenities.map(name => {
        const {icon, bg, fg} = styleFor(name, colors);
        return (
          <View key={name} style={{width: '25%', paddingHorizontal: spacing.xs, marginBottom: spacing.md}}>
            <View
              style={{
                width: TILE,
                height: TILE,
                borderRadius: radius.md,
                backgroundColor: bg,
                alignItems: 'center',
                justifyContent: 'center',
              }}>
              <Icon name={icon} size={moderateScale(22)} color={fg} />
            </View>
            <AppText
              variant="caption"
              color={colors.textSecondary}
              numberOfLines={2}
              style={{marginTop: moderateScale(4)}}>
              {name}
            </AppText>
          </View>
        );
      })}
    </View>
  );
};

export default AmenityGrid;
