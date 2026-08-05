import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {roundedRadius, useAppTheme} from '../theme';
import AppText from './AppText';
import AttachmentList from './AttachmentList';
import Badge from './Badge';
import Card from './Card';
import InfoRow from './InfoRow';
import UnitTypeTable from './UnitTypeTable';

function formatPrice(value) {
  return new Intl.NumberFormat('en-US').format(value ?? 0);
}

const SectionTitle = ({children, spacing, hint, colors}) => (
  <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
    <AppText variant="h3">{children}</AppText>
    {!!hint && (
      <AppText variant="caption" color={colors.textMuted} style={{marginTop: moderateScale(2)}}>
        {hint}
      </AppText>
    )}
  </View>
);

/** A section renders only if it has at least one populated value. */
function hasAny(obj, keys) {
  return keys.some(key => {
    const value = obj?.[key];
    return Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && value !== '';
  });
}

const PropertyDetailBody = ({project}) => {
  const {colors, spacing} = useAppTheme();
  // Pulled from context rather than threaded through as a prop: this component is
  // rendered by two screens in two different navigators, and both already sit inside
  // a stack that owns the terms route.
  const navigation = useNavigation();
  const details = project.details ?? {};
  const {overview, unit, location, specs, approvals, sales} = details;

  const quickSpecs = [
    project.bedrooms ? {icon: 'bed-outline', label: `${project.bedrooms} Beds`} : null,
    project.bathrooms ? {icon: 'water-outline', label: `${project.bathrooms} Baths`} : null,
    project.areaSqft
      ? {icon: 'resize-outline', label: `${project.areaSqft.toLocaleString()} sqft`}
      : null,
  ].filter(Boolean);

  return (
    <View style={{paddingHorizontal: spacing.lg, marginTop: spacing.lg}}>
      <View style={{flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between'}}>
        <View style={{flex: 1}}>
          <AppText variant="overline" color={colors.textMuted}>
            {project.currency ?? 'AED'}
          </AppText>
          <View style={{flexDirection: 'row', alignItems: 'flex-end', flexWrap: 'wrap'}}>
            <AppText variant="h1" color={colors.primaryDark}>
              {formatPrice(project.price)}
            </AppText>
            <AppText
              variant="bodyMedium"
              color={colors.textSecondary}
              style={{marginLeft: moderateScale(4), marginBottom: moderateScale(2)}}>
              {project.priceUnit}
            </AppText>
          </View>
        </View>
        {(!!project.commissionPercent || !!project.fosCommissionPercent) && (
          <View style={{alignItems: 'flex-end'}}>
            {!!project.commissionPercent && (
              <Badge label={`${project.commissionPercent}% CP Commission`} tone="warning" />
            )}
            {!!project.fosCommissionPercent && (
              <View style={{marginTop: spacing.xs}}>
                <Badge label={`${project.fosCommissionPercent}% FOS Commission`} tone="primary" />
              </View>
            )}
          </View>
        )}
      </View>

      {overview && (
        <View style={{flexDirection: 'row', flexWrap: 'wrap', marginTop: spacing.sm}}>
          {[
            overview.projectStatus,
            overview.projectType,
            overview.possessionDate && `Possession: ${overview.possessionDate}`,
            overview.constructionProgress && `${overview.constructionProgress} built`,
            overview.reraNumber && `RERA ${overview.reraNumber}`,
          ]
            .filter(Boolean)
            .map(label => (
              <View key={label} style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
                <Badge label={label} tone="neutral" />
              </View>
            ))}
        </View>
      )}

      {!!quickSpecs.length && (
        <Card style={{marginTop: spacing.lg}}>
          <View style={{flexDirection: 'row', justifyContent: 'space-between'}}>
            {quickSpecs.map(spec => (
              <View key={spec.label} style={{alignItems: 'center', flex: 1}}>
                <Icon name={spec.icon} size={moderateScale(20)} color={colors.primary} />
                <AppText
                  variant="captionMedium"
                  color={colors.textSecondary}
                  style={{marginTop: moderateScale(6)}}>
                  {spec.label}
                </AppText>
              </View>
            ))}
          </View>
        </Card>
      )}

      {/* ---------------------------------------------------------------- developer terms */}
      {!!project.hasTerms && !!project.terms && (
        <TouchableOpacity
          activeOpacity={0.85}
          onPress={() =>
            navigation.navigate('ProjectTerms', {
              terms: project.terms,
              projectName: project.name,
            })
          }
          style={{marginTop: spacing.lg}}>
          <Card style={{flexDirection: 'row', alignItems: 'center'}}>
            <View
              style={{
                width: moderateScale(40),
                height: moderateScale(40),
                borderRadius: roundedRadius.statusIcon,
                backgroundColor: colors.primarySoft,
                alignItems: 'center',
                justifyContent: 'center',
              }}>
              <Icon
                name={project.terms.type === 'document' ? 'document-text-outline' : 'reader-outline'}
                size={moderateScale(19)}
                color={colors.primaryDark}
              />
            </View>

            <View style={{flex: 1, marginLeft: spacing.sm}}>
              <AppText variant="bodyMedium" numberOfLines={1}>
                {project.terms.title}
              </AppText>
              <AppText
                variant="caption"
                color={colors.textMuted}
                numberOfLines={1}
                style={{marginTop: moderateScale(1)}}>
                {project.terms.type === 'document'
                  ? `${(project.terms.documentExtension ?? 'file').toUpperCase()} · tap to read or download`
                  : project.terms.excerpt || 'Tap to read the full terms'}
              </AppText>
            </View>

            <Icon name="chevron-forward" size={moderateScale(18)} color={colors.textMuted} />
          </Card>
        </TouchableOpacity>
      )}

      {!!project.description && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            About this project
          </SectionTitle>
          <AppText variant="body" color={colors.textSecondary}>
            {project.description}
          </AppText>
        </>
      )}
      {!!overview?.tagline && (
        <AppText variant="bodyMedium" color={colors.primaryDark} style={{marginTop: spacing.xs}}>
          “{overview.tagline}”
        </AppText>
      )}

      {/* ---------------------------------------------------------------- unit types */}
      {!!project.unitTypes?.length && (
        <>
          <SectionTitle
            spacing={spacing}
            colors={colors}
            hint={`${project.unitTypes.length} configuration${project.unitTypes.length === 1 ? '' : 's'} available`}>
            Unit Types &amp; Pricing
          </SectionTitle>
          <UnitTypeTable units={project.unitTypes} currency={project.currency} />
        </>
      )}

      {hasAny(unit, ['unitTypes', 'carpetArea', 'builtUpArea', 'superBuiltUpArea', 'pricePerSqft', 'totalUnits', 'towers', 'floorsPerTower', 'flatsPerFloor', 'parking']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Project Scale
          </SectionTitle>
          <Card>
            <InfoRow icon="home-outline" label="Unit Types" value={unit.unitTypes} />
            <InfoRow icon="scan-outline" label="Carpet Area" value={unit.carpetArea} />
            <InfoRow icon="scan-outline" label="Built-up Area" value={unit.builtUpArea} />
            <InfoRow icon="expand-outline" label="Super Built-up Area" value={unit.superBuiltUpArea} />
            <InfoRow icon="pricetag-outline" label="Price per Sqft" value={unit.pricePerSqft} />
            <InfoRow icon="business-outline" label="Total Units" value={unit.totalUnits} />
            <InfoRow icon="layers-outline" label="Towers / Blocks" value={unit.towers} />
            <InfoRow icon="layers-outline" label="Floors per Tower" value={unit.floorsPerTower} />
            <InfoRow icon="apps-outline" label="Flats per Floor" value={unit.flatsPerFloor} />
            <InfoRow icon="car-outline" label="Parking" value={unit.parking} />
          </Card>
        </>
      )}

      {/* ---------------------------------------------------------------- location */}
      {hasAny(location, ['locality', 'fullAddress', 'landmark', 'pincode', 'zone', 'connectivity', 'nearbyInfrastructure', 'coordinates']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Location Details
          </SectionTitle>
          <Card>
            <InfoRow icon="location-outline" label="Locality / Area" value={location.locality} />
            <InfoRow icon="navigate-outline" label="Full Address" value={location.fullAddress} />
            <InfoRow icon="flag-outline" label="Landmark" value={location.landmark} />
            <InfoRow icon="compass-outline" label="Zone" value={location.zone} />
            <InfoRow icon="mail-open-outline" label="Pincode" value={location.pincode} />
            <InfoRow icon="trail-sign-outline" label="Connectivity" value={location.connectivity} />
            <InfoRow icon="school-outline" label="Nearby Infrastructure" value={location.nearbyInfrastructure} />
            <InfoRow icon="pin-outline" label="Coordinates" value={location.coordinates} />
          </Card>
        </>
      )}

      {/* ---------------------------------------------------------------- specs */}
      {hasAny(specs, ['totalProjectArea', 'landParcel', 'constructionSpecs', 'amenitiesSize', 'amenitiesCount', 'greenCertification', 'vastuCompliant', 'awards']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Project Specifications
          </SectionTitle>
          <Card>
            <InfoRow icon="map-outline" label="Total Project Area" value={specs.totalProjectArea} />
            <InfoRow icon="square-outline" label="Land Parcel" value={specs.landParcel} />
            <InfoRow icon="construct-outline" label="Construction Specs" value={specs.constructionSpecs} />
            <InfoRow icon="fitness-outline" label="Clubhouse / Amenity Size" value={specs.amenitiesSize} />
            <InfoRow icon="list-outline" label="Amenities Count" value={specs.amenitiesCount} />
            <InfoRow icon="ribbon-outline" label="Green Certification" value={specs.greenCertification} />
            <InfoRow icon="compass-outline" label="Vastu Compliant" value={specs.vastuCompliant} />
            <InfoRow icon="trophy-outline" label="Awards" value={specs.awards} />
          </Card>
        </>
      )}

      {!!project.amenities?.length && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Amenities
          </SectionTitle>
          <View style={{flexDirection: 'row', flexWrap: 'wrap'}}>
            {project.amenities.map(amenity => (
              <View key={amenity} style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
                <Badge label={amenity} tone="neutral" />
              </View>
            ))}
          </View>
        </>
      )}

      {/* ---------------------------------------------------------------- approvals */}
      {hasAny(approvals, ['approvingAuthorities', 'bankApprovals']) ||
      hasAny(overview, ['reraNumber', 'reraRegisteredAt', 'reraValidTill']) ? (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Approvals &amp; Legal
          </SectionTitle>
          <Card>
            <InfoRow icon="shield-checkmark-outline" label="RERA Number" value={overview?.reraNumber} />
            <InfoRow icon="calendar-outline" label="RERA Registered" value={overview?.reraRegisteredAt} />
            <InfoRow icon="alarm-outline" label="RERA Valid Till" value={overview?.reraValidTill} />
            <InfoRow icon="ribbon-outline" label="Approving Authorities" value={approvals?.approvingAuthorities} />
            <InfoRow icon="card-outline" label="Bank Approvals" value={approvals?.bankApprovals} />
          </Card>
        </>
      ) : null}

      {/* ---------------------------------------------------------------- attachments */}
      {!!project.plans?.length && (
        <>
          <SectionTitle spacing={spacing} colors={colors} hint="Opens in your device viewer">
            Plans &amp; Layouts
          </SectionTitle>
          <AttachmentList items={project.plans} />
        </>
      )}

      {!!project.documents?.length && (
        <>
          <SectionTitle spacing={spacing} colors={colors} hint="Brochures, price lists and certificates">
            Documents
          </SectionTitle>
          <AttachmentList items={project.documents} />
        </>
      )}

      {!!project.tours?.length && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Video &amp; Virtual Tour
          </SectionTitle>
          <AttachmentList items={project.tours} />
        </>
      )}

      {/* ---------------------------------------------------------------- sales */}
      {hasAny(sales, ['officeAddress', 'visitTimings', 'contactName', 'contactNumber', 'bookingProcess']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Sales &amp; Booking
          </SectionTitle>
          <Card>
            <InfoRow icon="business-outline" label="Sales Office Address" value={sales.officeAddress} />
            <InfoRow icon="time-outline" label="Site Visit Timings" value={sales.visitTimings} />
            <InfoRow icon="person-outline" label="Contact Person" value={sales.contactName} />
            <InfoRow icon="call-outline" label="Contact Number" value={sales.contactNumber} />
            <InfoRow icon="clipboard-outline" label="Booking Process" value={sales.bookingProcess} />
          </Card>
        </>
      )}
    </View>
  );
};

export default PropertyDetailBody;
