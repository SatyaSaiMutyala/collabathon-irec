import React from 'react';
import {Alert, Linking, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Card from './Card';

/**
 * One card per unit type (`property_unit_types`), rather than the single flattened row
 * the detail screen used to show. A project with 1BHK/2BHK/3BHK has three different
 * areas and three different price bands, and collapsing them to the first row was
 * quietly misreporting the other two.
 */
const UnitTypeTable = ({units = [], currency = 'AED'}) => {
  const {colors, spacing, radius} = useAppTheme();

  if (!units.length) {
    return null;
  }

  const priceText = unit => {
    if (!unit.priceMin && !unit.priceMax) {
      return null;
    }
    const min = Number(unit.priceMin ?? 0).toLocaleString();
    if (unit.priceMax && unit.priceMax !== unit.priceMin) {
      return `${currency} ${min} – ${Number(unit.priceMax).toLocaleString()}`;
    }
    return `${currency} ${min}`;
  };

  const openPlan = async url => {
    try {
      await Linking.openURL(url);
    } catch {
      Alert.alert('Cannot open', 'This floor plan could not be opened.');
    }
  };

  const metrics = unit =>
    [
      ['Carpet', unit.carpetArea],
      ['Built-up', unit.builtUpArea],
      ['Super built-up', unit.superBuiltUpArea],
    ].filter(([, value]) => !!value);

  return (
    <View>
      {units.map((unit, index) => (
        <Card
          key={unit.id ?? index}
          style={{marginBottom: index === units.length - 1 ? 0 : spacing.sm}}>
          <View style={{flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'}}>
            <View style={{flex: 1}}>
              <AppText variant="h3">{unit.label ?? `Unit type ${index + 1}`}</AppText>
              {!!priceText(unit) && (
                <AppText variant="bodyMedium" color={colors.primaryDark} style={{marginTop: moderateScale(2)}}>
                  {priceText(unit)}
                </AppText>
              )}
            </View>
            {!!unit.unitsCount && (
              <View
                style={{
                  paddingHorizontal: spacing.xs,
                  paddingVertical: moderateScale(4),
                  borderRadius: radius.pill,
                  backgroundColor: colors.surface,
                }}>
                <AppText variant="caption" color={colors.textSecondary}>
                  {unit.unitsCount} units
                </AppText>
              </View>
            )}
          </View>

          {!!metrics(unit).length && (
            <View
              style={{
                flexDirection: 'row',
                marginTop: spacing.sm,
                paddingTop: spacing.sm,
                borderTopWidth: 1,
                borderTopColor: colors.border,
              }}>
              {metrics(unit).map(([label, value]) => (
                <View key={label} style={{flex: 1}}>
                  <AppText variant="caption" color={colors.textMuted}>
                    {label}
                  </AppText>
                  <AppText variant="captionMedium" style={{marginTop: moderateScale(1)}}>
                    {value}
                  </AppText>
                </View>
              ))}
            </View>
          )}

          {!!unit.floorPlanUrl && (
            <TouchableOpacity
              activeOpacity={0.7}
              onPress={() => openPlan(unit.floorPlanUrl)}
              style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
              <Icon name="layers-outline" size={moderateScale(16)} color={colors.primary} />
              <AppText variant="captionMedium" color={colors.primary} style={{marginLeft: spacing.xxs}}>
                View floor plan
              </AppText>
            </TouchableOpacity>
          )}
        </Card>
      ))}
    </View>
  );
};

export default UnitTypeTable;
