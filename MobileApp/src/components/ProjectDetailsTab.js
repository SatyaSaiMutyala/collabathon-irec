import React from 'react';
import {Alert, Linking, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AmenityGrid from './AmenityGrid';
import AppText from './AppText';
import EmptyState from './EmptyState';
import RemoteImage from './RemoteImage';
import UnitTypeTable from './UnitTypeTable';

function formatPrice(value) {
  return new Intl.NumberFormat('en-IN').format(value ?? 0);
}

/** icon + label on the left, bold value on the right, one line — the "Key details"
 * panel's own row shape, distinct from InfoRow's stacked or compact layouts: every
 * value here is short enough to sit flush right, which InfoRow doesn't do. */
const KeyDetailRow = ({icon, label, value, isLast}) => {
  const {colors, spacing} = useAppTheme();
  if (!value) {
    return null;
  }

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.sm,
        borderBottomWidth: isLast ? 0 : 1,
        borderBottomColor: colors.border,
      }}>
      <Icon name={icon} size={moderateScale(18)} color={colors.textSecondary} />
      <AppText variant="body" color={colors.textSecondary} style={{marginLeft: spacing.sm, flex: 1}}>
        {label}
      </AppText>
      <AppText variant="bodyMedium" weight="semiBold">
        {value}
      </AppText>
    </View>
  );
};

const THUMB = moderateScale(46);

/** One row per unit type that actually has its own floor plan image — the same
 * per-configuration plans UnitTypeTable already links out to below, previewed here. */
const FloorPlanRow = ({unit, currency, isLast}) => {
  const {colors, spacing} = useAppTheme();

  const area = unit.carpetAreaSqft ?? unit.builtUpAreaSqft ?? unit.superBuiltUpAreaSqft;
  const subtitle = area
    ? `${Number(area).toLocaleString()} sq.ft.`
    : unit.priceMin
      ? `${currency} ${Number(unit.priceMin).toLocaleString()} onwards`
      : null;

  const open = async () => {
    try {
      await Linking.openURL(unit.floorPlanUrl);
    } catch {
      Alert.alert('Cannot open', 'This floor plan could not be opened.');
    }
  };

  return (
    <TouchableOpacity
      activeOpacity={0.7}
      onPress={open}
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.sm,
        borderBottomWidth: isLast ? 0 : 1,
        borderBottomColor: colors.border,
      }}>
      <RemoteImage
        uri={unit.floorPlanUrl}
        style={{width: THUMB, height: THUMB, borderRadius: moderateScale(6)}}
        resizeMode="cover"
        fallback={
          <View
            style={{
              width: THUMB,
              height: THUMB,
              borderRadius: moderateScale(6),
              backgroundColor: colors.surface,
              alignItems: 'center',
              justifyContent: 'center',
            }}>
            <Icon name="layers-outline" size={moderateScale(18)} color={colors.textMuted} />
          </View>
        }
      />
      <View style={{flex: 1, marginLeft: spacing.sm}}>
        <AppText variant="bodyMedium" numberOfLines={1}>
          Typical {unit.label} Plan
        </AppText>
        {!!subtitle && (
          <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
            {subtitle}
          </AppText>
        )}
      </View>
      <Icon name="chevron-forward" size={moderateScale(16)} color={colors.textMuted} />
    </TouchableOpacity>
  );
};

/**
 * Details: the key facts panel (residence/price/scale), amenities, a floor-plan
 * preview per configuration, and the full Residences & Pricing breakdown below it —
 * the last one already covered price/area/facing/units-count per configuration before
 * this redesign, and stays exactly as it was so none of that is lost, just relocated.
 */
const ProjectDetailsTab = ({project}) => {
  const {colors, spacing, radius} = useAppTheme();
  const {configuration, specs} = project.details ?? {};
  const configLabel = (project.unitTypes ?? []).map(u => u.label).filter(Boolean).join(', ');
  const priceSuffix = project.priceUnit?.startsWith('–') ? '' : project.priceUnit;
  const priceValue = project.price
    ? [project.currency ?? 'INR', formatPrice(project.price), priceSuffix].filter(Boolean).join(' ')
    : null;
  const plansWithImages = (project.unitTypes ?? []).filter(u => !!u.floorPlanUrl);

  const keyDetails = [
    {icon: 'home-outline', label: 'Residence', value: configLabel},
    {icon: 'pricetag-outline', label: 'Price', value: priceValue},
    {icon: 'business-outline', label: 'Total units', value: configuration?.totalUnits},
    {icon: 'layers-outline', label: 'Total towers', value: configuration?.towers},
    {icon: 'albums-outline', label: 'Total floors', value: configuration?.floors},
    {icon: 'square-outline', label: 'Total land area', value: specs?.landParcel},
    {icon: 'ribbon-outline', label: 'Green certified', value: specs?.greenCertification},
    {icon: 'compass-outline', label: 'Vastu compliant', value: specs?.vastuCompliant},
  ].filter(row => !!row.value);

  if (!keyDetails.length && !project.amenities?.length && !plansWithImages.length && !project.unitTypes?.length) {
    return (
      <EmptyState
        icon="reader-outline"
        title="No details added yet"
        message="Residence, scale, amenities and floor plans will show here once the developer adds them."
        style={{marginTop: spacing.xxxl}}
      />
    );
  }

  return (
    <View style={{paddingHorizontal: spacing.lg, paddingTop: spacing.xs}}>
      {!!keyDetails.length && (
        <>
          <AppText variant="h3" style={{marginBottom: spacing.sm}}>
            Key details
          </AppText>
          <View style={{backgroundColor: colors.surface, borderRadius: radius.lg, paddingHorizontal: spacing.md}}>
            {keyDetails.map((row, index) => (
              <KeyDetailRow key={row.label} {...row} isLast={index === keyDetails.length - 1} />
            ))}
          </View>
        </>
      )}

      {!!project.amenities?.length && (
        <View style={{marginTop: spacing.xl}}>
          <AppText variant="h3" style={{marginBottom: spacing.md}}>
            Amenities
          </AppText>
          <AmenityGrid amenities={project.amenities} />
        </View>
      )}

      {!!plansWithImages.length && (
        <View style={{marginTop: spacing.lg}}>
          <AppText variant="h3" style={{marginBottom: spacing.sm}}>
            Floor plans
          </AppText>
          <View style={{backgroundColor: colors.surface, borderRadius: radius.lg, paddingHorizontal: spacing.md}}>
            {plansWithImages.map((unit, index) => (
              <FloorPlanRow
                key={unit.id ?? index}
                unit={unit}
                currency={project.currency ?? 'INR'}
                isLast={index === plansWithImages.length - 1}
              />
            ))}
          </View>
        </View>
      )}

      {!!project.unitTypes?.length && (
        <View style={{marginTop: spacing.xl}}>
          <AppText variant="h3" style={{marginBottom: spacing.sm}}>
            Residences &amp; Pricing
          </AppText>
          <UnitTypeTable units={project.unitTypes} currency={project.currency} />
        </View>
      )}
    </View>
  );
};

export default ProjectDetailsTab;
