import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {openLink} from '../utils/openLink';
import {roundedRadius, useAppTheme} from '../theme';
import AppText from './AppText';
import AttachmentList from './AttachmentList';
import Badge from './Badge';
import Card from './Card';
import InfoRow from './InfoRow';
import UnitTypeTable from './UnitTypeTable';

// en-IN — matches the max half of the same range in `project.priceUnit`
// (normalizers.js), same reasoning as PropertyCard's own formatPrice.
function formatPrice(value) {
  return new Intl.NumberFormat('en-IN').format(value ?? 0);
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

/**
 * `highlightCommission`: the channel-partner view (broker/ProjectDetailScreen) sets this
 * true — a CP's reason to open a project is what it pays *them*, so commission gets a
 * coloured, full-width strip instead of a plain badge. The developer's own view
 * (PropertyLeadsScreen) leaves it false and keeps the small badge it always had.
 */
const PropertyDetailBody = ({project, highlightCommission = false}) => {
  const {colors, spacing} = useAppTheme();
  // Pulled from context rather than threaded through as a prop: this component is
  // rendered by two screens in two different navigators, and both already sit inside
  // a stack that owns the terms route.
  const navigation = useNavigation();
  const details = project.details ?? {};
  const {overview, configuration, location, specs, sales} = details;
  // A legacy two-field record's priceUnit carries a "– <max>" range suffix (see
  // normalizers.js) — correct on the admin sheet this mirrors, but reads as a stray
  // dash sitting under the price on a compact mobile card. Only "onwards" (or nothing)
  // shows here; the range itself is still the real number underneath if anyone taps in.
  const priceSuffix = project.priceUnit?.startsWith('–') ? '' : project.priceUnit;
  // The server already sends a starred sales_contact_number when this is false — see
  // PropertyDetailResource::withContact() — so this only decides how the row itself
  // reads (muted, with an explanatory note), not whether the number is real.
  const contactVisible = project.developer?.contact_visible ?? false;

  return (
    <View style={{paddingHorizontal: spacing.lg, marginTop: spacing.lg}}>
      <View style={{flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between'}}>
        <View style={{flex: 1}}>
          {/* Currency here, "onwards" on the suffix below — together they read as the
              admin sheet's "From ₹X". */}
          <AppText variant="overline" color={colors.textMuted}>
            {project.currency ?? 'INR'}
          </AppText>
          <View style={{flexDirection: 'row', alignItems: 'flex-end', flexWrap: 'wrap'}}>
            <AppText variant="h1" color={colors.primaryDark}>
              {formatPrice(project.price)}
            </AppText>
            {!!priceSuffix && (
              <AppText
                variant="bodyMedium"
                color={colors.textSecondary}
                style={{marginLeft: moderateScale(4), marginBottom: moderateScale(2)}}>
                {priceSuffix}
              </AppText>
            )}
          </View>
        </View>
        {!highlightCommission && (!!project.commissionPercent || !!project.fosCommissionPercent) && (
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

      {highlightCommission && !!project.commissionPercent && (
        // Bold coloured text, not a filled box — a solid, full-width, centred pill is
        // exactly Button's own shape (see components/Button.js), and this isn't tappable.
        // Left-aligned inline text with an icon reads as emphasis, not as a control.
        <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.xs}}>
          <Icon name="cash-outline" size={moderateScale(18)} color={colors.warning} />
          <AppText
            variant="h3"
            color={colors.warning}
            style={{marginLeft: moderateScale(6)}}>
            {project.commissionPercent}% CP Commission
          </AppText>
          {!!project.fosCommissionPercent && (
            <AppText variant="caption" color={colors.textMuted} style={{marginLeft: moderateScale(6)}}>
              (+{project.fosCommissionPercent}% FOS)
            </AppText>
          )}
        </View>
      )}

      {overview && (
        <View style={{flexDirection: 'row', flexWrap: 'wrap', marginTop: spacing.sm}}>
          {[
            overview.projectStatus,
            overview.projectType,
            overview.possessionDate && `Possession: ${overview.possessionDate}`,
          ]
            .filter(Boolean)
            .map(label => (
              <View key={label} style={{marginRight: spacing.xs, marginBottom: spacing.xs}}>
                <Badge label={label} tone="neutral" />
              </View>
            ))}
        </View>
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
            About the Residence
          </SectionTitle>
          <AppText variant="body" color={colors.textSecondary}>
            {project.description}
          </AppText>
        </>
      )}
      {!!overview?.tagline && (
        <AppText variant="bodyMedium" color={colors.primaryDark} style={{marginTop: spacing.xs}}>
          {overview.tagline}
        </AppText>
      )}

      {/* ---------------------------------------------------------------- unit types */}
      {!!project.unitTypes?.length && (
        <>
          <SectionTitle
            spacing={spacing}
            colors={colors}
            hint={`${project.unitTypes.length} configuration${project.unitTypes.length === 1 ? '' : 's'} available`}>
            Residences &amp; Pricing
          </SectionTitle>
          <UnitTypeTable units={project.unitTypes} currency={project.currency} />
        </>
      )}

      {hasAny(configuration, ['extentMetric', 'totalUnits', 'towers', 'floors']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Configuration
          </SectionTitle>
          <Card>
            <InfoRow icon="resize-outline" label="Project Extent Metric" value={configuration.extentMetric} />
            <InfoRow icon="business-outline" label="Total Units" value={configuration.totalUnits} />
            <InfoRow icon="layers-outline" label="Towers / Blocks" value={configuration.towers} />
            <InfoRow icon="git-commit-outline" label="No. of Floors" value={configuration.floors} />
          </Card>
        </>
      )}

      {/* ---------------------------------------------------------------- location */}
      {hasAny(location, ['city', 'state', 'locality', 'fullAddress', 'landmark', 'pincode', 'zone', 'mapsLink', 'connectivity', 'nearbyInfrastructure', 'coordinates']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Address &amp; Connectivity
          </SectionTitle>
          <Card>
            <InfoRow icon="business-outline" label="City" value={location.city} />
            <InfoRow icon="map-outline" label="State" value={location.state} />
            <InfoRow icon="location-outline" label="Locality / Area" value={location.locality} />
            <InfoRow icon="navigate-outline" label="Full Address" value={location.fullAddress} />
            <InfoRow icon="flag-outline" label="Landmark" value={location.landmark} />
            <InfoRow icon="mail-open-outline" label="Pincode" value={location.pincode} />
            <InfoRow icon="compass-outline" label="Zone" value={location.zone} />
            <InfoRow icon="pin-outline" label="Coordinates" value={location.coordinates} />
            {/* Tappable rather than plain text: a maps URL is only useful when it opens. */}
            <InfoRow
              icon="map-outline"
              label="Google Maps"
              value={location.mapsLink ? 'Open in Maps' : null}
              valueColor={colors.primary}
              onPress={() => openLink(location.mapsLink)}
            />
            <InfoRow icon="trail-sign-outline" label="Connectivity" value={location.connectivity} />
            <InfoRow icon="school-outline" label="In the Vicinity" value={location.nearbyInfrastructure} />
          </Card>
        </>
      )}

      {/* ---------------------------------------------------------------- specs */}
      {hasAny(specs, ['landParcel', 'totalProjectArea', 'greenCertification', 'vastuCompliant']) && (
        <>
          <SectionTitle spacing={spacing} colors={colors}>
            Specifications
          </SectionTitle>
          <Card>
            <InfoRow icon="square-outline" label="Land Parcel" value={specs.landParcel} />
            <InfoRow icon="map-outline" label="Total Project Area" value={specs.totalProjectArea} />
            <InfoRow icon="ribbon-outline" label="Green Certification" value={specs.greenCertification} />
            <InfoRow icon="compass-outline" label="Vastu Compliant" value={specs.vastuCompliant} />
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
            {!contactVisible && !!sales.contactNumber && (
              <View style={{flexDirection: 'row', alignItems: 'flex-start', paddingBottom: spacing.sm}}>
                <Icon name="lock-closed" size={moderateScale(15)} color={colors.warning} />
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginLeft: moderateScale(8), flex: 1}}>
                  The last few digits are hidden until this developer accepts your
                  request. Accepting releases the full number.
                </AppText>
              </View>
            )}
            <InfoRow icon="business-outline" label="Sales Office Address" value={sales.officeAddress} />
            <InfoRow icon="time-outline" label="Site Visit Timings" value={sales.visitTimings} />
            <InfoRow icon="person-outline" label="Contact Person" value={sales.contactName} />
            <InfoRow
              icon="call-outline"
              label="Contact Number"
              value={sales.contactNumber}
              valueColor={contactVisible ? undefined : colors.textMuted}
            />
            <InfoRow icon="clipboard-outline" label="Booking Process" value={sales.bookingProcess} />
          </Card>
        </>
      )}
    </View>
  );
};

export default PropertyDetailBody;
