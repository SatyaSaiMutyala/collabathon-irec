import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Badge from './Badge';
import Card from './Card';
import InfoRow from './InfoRow';

function formatPrice(value) {
  return new Intl.NumberFormat('en-US').format(value);
}

const SectionTitle = ({children, spacing}) => (
  <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
    {children}
  </AppText>
);

const PropertyDetailBody = ({project}) => {
  const {colors, spacing} = useAppTheme();
  const details = project.details ?? {};
  const {overview, unit, location, specs, approvals, payment, sales} = details;

  const quickSpecs = [
    {icon: 'bed-outline', label: `${project.bedrooms} Beds`},
    {icon: 'water-outline', label: `${project.bathrooms} Baths`},
    {icon: 'resize-outline', label: `${project.areaSqft.toLocaleString()} sqft`},
  ];

  return (
    <View style={{paddingHorizontal: spacing.lg, marginTop: spacing.lg}}>
      <View style={{flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between'}}>
        <View>
          <AppText variant="overline" color={colors.textMuted}>
            AED
          </AppText>
          <View style={{flexDirection: 'row', alignItems: 'flex-end'}}>
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
        <Badge label={`${project.commissionPercent}% Commission`} tone="warning" />
      </View>

      {overview && (
        <View style={{flexDirection: 'row', flexWrap: 'wrap', marginTop: spacing.sm}}>
          {overview.projectStatus && (
            <View style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
              <Badge label={overview.projectStatus} tone="neutral" />
            </View>
          )}
          {overview.possessionDate && (
            <View style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
              <Badge label={`Possession: ${overview.possessionDate}`} tone="neutral" />
            </View>
          )}
          {overview.reraNumber && (
            <View style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
              <Badge label={overview.reraNumber} tone="neutral" />
            </View>
          )}
        </View>
      )}

      <Card style={{marginTop: spacing.lg}}>
        <View style={{flexDirection: 'row', justifyContent: 'space-between'}}>
          {quickSpecs.map(spec => (
            <View key={spec.label} style={{alignItems: 'center', flex: 1}}>
              <Icon name={spec.icon} size={moderateScale(20)} color={colors.primary} />
              <AppText variant="captionMedium" color={colors.textSecondary} style={{marginTop: moderateScale(6)}}>
                {spec.label}
              </AppText>
            </View>
          ))}
        </View>
      </Card>

      <SectionTitle spacing={spacing}>About this project</SectionTitle>
      <AppText variant="body" color={colors.textSecondary}>
        {project.description}
      </AppText>
      {overview?.tagline && (
        <AppText variant="bodyMedium" color={colors.primaryDark} style={{marginTop: spacing.xs}}>
          “{overview.tagline}”
        </AppText>
      )}

      {unit && (
        <>
          <SectionTitle spacing={spacing}>Unit &amp; Pricing Details</SectionTitle>
          <Card>
            <InfoRow icon="home-outline" label="Unit Types" value={unit.unitTypes} />
            <InfoRow icon="scan-outline" label="Carpet Area" value={unit.carpetArea} />
            <InfoRow icon="scan-outline" label="Built-up Area" value={unit.builtUpArea} />
            <InfoRow icon="expand-outline" label="Super Built-up Area" value={unit.superBuiltUpArea} />
            <InfoRow icon="pricetag-outline" label="Price per Sqft" value={unit.pricePerSqft} />
            <InfoRow icon="business-outline" label="Total Units" value={unit.totalUnits} />
            <InfoRow icon="layers-outline" label="Towers / Blocks" value={unit.towers} />
            <InfoRow icon="layers-outline" label="Floors per Tower" value={unit.floorsPerTower} />
            <InfoRow icon="car-outline" label="Parking" value={unit.parking} />
          </Card>
        </>
      )}

      {location && (
        <>
          <SectionTitle spacing={spacing}>Location Details</SectionTitle>
          <Card>
            <InfoRow icon="location-outline" label="Locality / Area" value={location.locality} />
            <InfoRow icon="navigate-outline" label="Full Address" value={location.fullAddress} />
            <InfoRow icon="flag-outline" label="Landmark" value={location.landmark} />
            <InfoRow icon="mail-open-outline" label="Pincode" value={location.pincode} />
            <InfoRow icon="trail-sign-outline" label="Connectivity" value={location.connectivity} />
            <InfoRow icon="school-outline" label="Nearby Infrastructure" value={location.nearbyInfrastructure} />
          </Card>
        </>
      )}

      {specs && (
        <>
          <SectionTitle spacing={spacing}>Project Specifications</SectionTitle>
          <Card>
            <InfoRow icon="map-outline" label="Total Project Area" value={specs.totalProjectArea} />
            <InfoRow icon="leaf-outline" label="Open / Green Space" value={specs.openSpace} />
            <InfoRow icon="construct-outline" label="Construction Specs" value={specs.constructionSpecs} />
            <InfoRow icon="ribbon-outline" label="Green Certification" value={specs.greenCertification} />
            <InfoRow icon="compass-outline" label="Vastu Compliant" value={specs.vastuCompliant} />
          </Card>
        </>
      )}

      <SectionTitle spacing={spacing}>Amenities</SectionTitle>
      <View style={{flexDirection: 'row', flexWrap: 'wrap'}}>
        {project.amenities.map(amenity => (
          <View key={amenity} style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
            <Badge label={amenity} tone="neutral" />
          </View>
        ))}
      </View>

      {approvals && (
        <>
          <SectionTitle spacing={spacing}>Approvals &amp; Legal</SectionTitle>
          <Card>
            <InfoRow icon="shield-checkmark-outline" label="Approving Authorities" value={approvals.approvingAuthorities} />
            <InfoRow icon="card-outline" label="Bank Approvals" value={approvals.bankApprovals} />
            <InfoRow icon="document-text-outline" label="Legal Due Diligence" value={approvals.legalDueDiligence} />
          </Card>
        </>
      )}

      {payment && (
        <>
          <SectionTitle spacing={spacing}>Payment &amp; Charges</SectionTitle>
          <Card>
            <InfoRow icon="cash-outline" label="Price Range" value={payment.priceRange} />
            <InfoRow icon="wallet-outline" label="Booking Amount" value={payment.bookingAmount} />
            <InfoRow icon="calendar-outline" label="Payment Plan" value={payment.paymentPlan} />
            <InfoRow icon="build-outline" label="Maintenance Charges" value={payment.maintenanceCharges} />
            <InfoRow icon="trending-up-outline" label="Floor Rise Charges" value={payment.floorRise} />
            <InfoRow icon="pricetags-outline" label="PLC Charges" value={payment.plcCharges} />
            <InfoRow icon="ellipsis-horizontal-outline" label="Other Charges" value={payment.otherCharges} />
            <InfoRow icon="receipt-outline" label="Registration & Stamp Duty" value={payment.stampDuty} />
          </Card>
        </>
      )}

      {sales && (
        <>
          <SectionTitle spacing={spacing}>Sales Contact</SectionTitle>
          <Card>
            <InfoRow icon="business-outline" label="Sales Office Address" value={sales.officeAddress} />
            <InfoRow icon="time-outline" label="Site Visit Timings" value={sales.visitTimings} />
            <InfoRow icon="person-outline" label="Contact Person" value={sales.contactName} />
            <InfoRow icon="call-outline" label="Contact Number" value={sales.contactNumber} />
          </Card>
        </>
      )}
    </View>
  );
};

export default PropertyDetailBody;
