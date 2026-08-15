import React from 'react';
import {Alert, Linking, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Card from './Card';

/**
 * One card per unit type (`property_unit_types`), rather than the single flattened row
 * the detail screen used to show. A project with 1BHK/2BHK/3BHK has three different
 * entry prices, and collapsing them to the first row was quietly misreporting the
 * other two.
 *
 * Each row carries what the intake form asks for and nothing else: the configuration,
 * the price it starts at, how many there are, and its floor plan. The carpet /
 * built-up / super built-up figures were dropped from the form, so they are not shown
 * here either — an area on screen for a field nobody can update is worse than no area.
 */
const UnitTypeTable = ({units = [], currency = 'INR'}) => {
  const {colors, spacing, radius} = useAppTheme();

  if (!units.length) {
    return null;
  }

  const priceText = unit =>
    unit.priceMin ? `${currency} ${Number(unit.priceMin).toLocaleString()} onwards` : null;

  const openPlan = async url => {
    try {
      await Linking.openURL(url);
    } catch {
      Alert.alert('Cannot open', 'This floor plan could not be opened.');
    }
  };

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
