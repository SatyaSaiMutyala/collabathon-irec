import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * The project detail screen's Overview/Details/Location/Files & Sales switch,
 * generalised in case another screen needs the same "switch which section renders
 * below" control later.
 *
 * `justifyContent: 'space-between'` spreads the 4 tabs evenly across the row, but each
 * tab itself is natural width — not `flex: 1` — so its own underline still matches
 * just that label's width instead of stretching across a whole equal-width column
 * (which read as disconnected from the word sitting inside it).
 *
 * Deliberately not scrollable and not badge-bearing like the Pending/Accepted pills
 * elsewhere in the app (RequestsScreen) — those are filters on a list; this swaps
 * which entire section of a screen is showing, closer to a native tab strip.
 */
const TabBar = ({tabs, activeIndex, onChange}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <View style={{flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: spacing.md}}>
      {tabs.map((label, index) => {
        const active = index === activeIndex;
        return (
          <TouchableOpacity
            key={label}
            activeOpacity={0.7}
            onPress={() => onChange(index)}
            style={{alignItems: 'center', paddingBottom: spacing.xs}}>
            <AppText
              variant="bodyMedium"
              weight={active ? 'semiBold' : undefined}
              color={active ? colors.primary : colors.textMuted}
              numberOfLines={1}>
              {label}
            </AppText>
            <View
              style={{
                marginTop: spacing.xxs,
                height: moderateScale(3),
                alignSelf: 'stretch',
                backgroundColor: active ? colors.primary : 'transparent',
              }}
            />
          </TouchableOpacity>
        );
      })}
    </View>
  );
};

export default TabBar;
