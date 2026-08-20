import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * Short, scalar facts — a pincode, a floor count, a join year — shown two to a
 * row instead of `InfoRow`'s one-per-line. Same {icon, label, value, valueColor,
 * onPress} item shape as InfoRow (and filters out unset values the same way), so
 * a section can mix both: put the short fields here and leave anything that needs
 * room to wrap (an address, a booking process paragraph) as InfoRow around it.
 * That split, not a wholesale swap, is what actually shortens a page built from a
 * dozen short fields — cramming a long value into a half-width tile just wraps
 * awkwardly instead of reading compact.
 *
 * Paired into real row Views (not a flex-wrap of independently-sized tiles): two
 * tiles whose labels wrap to a different number of lines — "Total Units" fits on
 * one, "Towers / Blocks" needs two — must still end up the same height as each
 * other. Flex's default `alignItems: 'stretch'` only does that for siblings that
 * genuinely share a row container, so each pair gets its own.
 */
const InfoGrid = ({items}) => {
  const {colors, spacing, radius} = useAppTheme();
  const visible = items.filter(item => !!item.value);

  if (!visible.length) {
    return null;
  }

  const rows = [];
  for (let i = 0; i < visible.length; i += 2) {
    rows.push(visible.slice(i, i + 2));
  }

  const renderTile = item => {
    const Wrapper = item.onPress ? TouchableOpacity : View;

    return (
      <Wrapper
        {...(item.onPress ? {activeOpacity: 0.7, onPress: item.onPress} : {})}
        style={{
          flex: 1,
          flexDirection: 'row',
          alignItems: 'flex-start',
          backgroundColor: colors.surface,
          borderRadius: radius.md,
          paddingVertical: spacing.sm,
          paddingHorizontal: spacing.sm,
          minHeight: moderateScale(56),
        }}>
        {!!item.icon && (
          <Icon
            name={item.icon}
            size={moderateScale(14)}
            color={colors.primary}
            style={{marginTop: moderateScale(2)}}
          />
        )}
        <View style={{marginLeft: item.icon ? spacing.xs : 0, flex: 1}}>
          {/* Two lines, not one — a half-width tile is narrow enough that even a
              short label ("Contact Person", "Key Contact Designation") can run
              past it, and a mid-word ellipsis reads as broken, not compact. */}
          <AppText variant="caption" color={colors.textMuted} numberOfLines={2}>
            {item.label}
          </AppText>
          <AppText
            variant="bodyMedium"
            color={item.valueColor}
            numberOfLines={2}
            style={{marginTop: moderateScale(1)}}>
            {item.value}
          </AppText>
        </View>
      </Wrapper>
    );
  };

  return (
    <View>
      {rows.map((row, rowIndex) => (
        <View key={rowIndex} style={{flexDirection: 'row', marginBottom: rowIndex < rows.length - 1 ? spacing.xs * 2 : 0}}>
          <View style={{flex: 1, marginRight: spacing.xs}}>{renderTile(row[0])}</View>
          {row[1] ? (
            <View style={{flex: 1, marginLeft: spacing.xs}}>{renderTile(row[1])}</View>
          ) : (
            <View style={{flex: 1, marginLeft: spacing.xs}} />
          )}
        </View>
      ))}
    </View>
  );
};

export default InfoGrid;
