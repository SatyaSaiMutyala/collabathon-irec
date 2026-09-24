import React from 'react';
import {Alert, Linking, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import EmptyState from './EmptyState';

// Colour + glyph per upload kind (see normalizers.js's MEDIA_KINDS for the full kind
// list) — deliberately not AttachmentList, which ProfileScreen also uses for a
// broker's own KYC documents; recolouring it there wasn't asked for and a kind like
// "unit_plan" means nothing in that context anyway. This stays local to this one tab.
const KIND_STYLE = {
  site_layout: {icon: 'map', tone: 'success'},
  master_plan: {icon: 'grid', tone: 'cyan'},
  floor_plan: {icon: 'layers', tone: 'cyan'},
  unit_plan: {icon: 'document-text', tone: 'pink'},
  brochure: {icon: 'document-text', tone: 'yellow'},
  price_list: {icon: 'pricetags', tone: 'orange'},
  payment_schedule: {icon: 'calendar', tone: 'primary'},
  rera_certificate: {icon: 'shield-checkmark', tone: 'danger'},
  video: {icon: 'play', tone: 'primary'},
  virtual_tour: {icon: 'cube', tone: 'cyan'},
};

const DocumentRow = ({item, isLast}) => {
  const {colors, radius, spacing} = useAppTheme();
  const style = KIND_STYLE[item.kind] ?? {icon: 'document-text', tone: 'primary'};
  const toneMap = {
    primary: {bg: colors.primarySoft, fg: colors.primaryDark},
    success: {bg: colors.successSoft, fg: colors.success},
    danger: {bg: colors.dangerSoft, fg: colors.danger},
    cyan: {bg: colors.accentCyanSoft, fg: colors.accentCyan},
    yellow: {bg: colors.accentYellowSoft, fg: colors.accentOrange},
    orange: {bg: colors.accentOrangeSoft, fg: colors.accentOrange},
    pink: {bg: colors.accentPinkSoft, fg: colors.accentPink},
  };
  const {bg, fg} = toneMap[style.tone];

  const open = async () => {
    try {
      await Linking.openURL(item.url);
    } catch {
      Alert.alert('Cannot open', 'No app on this device could open this file, or the link is no longer valid.');
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
      <View
        style={{
          width: moderateScale(40),
          height: moderateScale(40),
          borderRadius: radius.sm,
          backgroundColor: bg,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <Icon name={style.icon} size={moderateScale(18)} color={fg} />
      </View>
      <AppText variant="bodyMedium" style={{flex: 1, marginLeft: spacing.sm}} numberOfLines={1}>
        {item.label}
      </AppText>
      <Icon name="download-outline" size={moderateScale(19)} color={colors.primary} />
    </TouchableOpacity>
  );
};

/** Icon-disc + label-over-value, the sales panel's own row shape — distinct from
 * InfoRow's icon-beside-text because the reference groups Sales office/Visiting hours
 * as short facts under a round marker rather than a form-style list. */
const SalesFact = ({icon, label, value, isLast, valueColor}) => {
  const {colors, spacing} = useAppTheme();
  if (!value) {
    return null;
  }

  return (
    <View style={{flexDirection: 'row', alignItems: 'flex-start', marginBottom: isLast ? 0 : spacing.md}}>
      <View
        style={{
          width: moderateScale(40),
          height: moderateScale(40),
          borderRadius: moderateScale(999),
          backgroundColor: colors.primarySoft,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <Icon name={icon} size={moderateScale(18)} color={colors.primaryDark} />
      </View>
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium" color={valueColor} style={{marginTop: moderateScale(1)}}>
          {value}
        </AppText>
      </View>
    </View>
  );
};

/**
 * Files & Sales: every attachment (plans, documents, video/virtual tour — three
 * separate sections before this redesign) as one combined "Project documents" list,
 * plus the sales desk's own contact facts.
 *
 * Contact Person/Number stay here with their existing privacy gate even though the
 * reference mockup's example project didn't have one filled in to show — dropping a
 * real, currently-masked field because one illustration happened not to have data
 * would be a functional regression, not a design change.
 */
const ProjectFilesSalesTab = ({project}) => {
  const {colors, spacing, radius} = useAppTheme();
  const sales = project.details?.sales ?? {};
  const contactVisible = project.developer?.contact_visible ?? false;
  const documents = [...(project.plans ?? []), ...(project.documents ?? []), ...(project.tours ?? [])];
  const hasSalesInfo =
    !!sales.officeAddress || !!sales.visitTimings || !!sales.contactName || !!sales.contactNumber;

  if (!documents.length && !hasSalesInfo) {
    return (
      <EmptyState
        icon="document-text-outline"
        title="No files or sales info yet"
        message="Plans, brochures and sales desk details will show here once the developer adds them."
        style={{marginTop: spacing.xxxl}}
      />
    );
  }

  return (
    <View style={{paddingHorizontal: spacing.lg, paddingTop: spacing.xs}}>
      {!!documents.length && (
        <>
          <AppText variant="h3" style={{marginBottom: spacing.sm}}>
            Project documents
          </AppText>
          <View style={{backgroundColor: colors.surface, borderRadius: radius.lg, paddingHorizontal: spacing.md}}>
            {documents.map((item, index) => (
              <DocumentRow key={item.id ?? index} item={item} isLast={index === documents.length - 1} />
            ))}
          </View>
        </>
      )}

      {hasSalesInfo && (
        <View style={{marginTop: spacing.xl}}>
          <AppText variant="h3" style={{marginBottom: spacing.md}}>
            Sales information
          </AppText>

          {!contactVisible && !!sales.contactNumber && (
            <View style={{flexDirection: 'row', alignItems: 'flex-start', marginBottom: spacing.md}}>
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

          <SalesFact icon="location" label="Sales office" value={sales.officeAddress} />
          <SalesFact icon="time" label="Visiting hours" value={sales.visitTimings} />
          <SalesFact icon="person" label="Contact person" value={sales.contactName} />
          <SalesFact
            icon="call"
            label="Contact number"
            value={sales.contactNumber}
            valueColor={contactVisible ? undefined : colors.textMuted}
            isLast
          />

          {/* A helpful default when this developer hasn't written their own booking
              steps — generic guidance, not a claim about this specific project, so it
              stays honest without depending on a field that might be empty. */}
          <View
            style={{
              flexDirection: 'row',
              alignItems: 'flex-start',
              marginTop: spacing.md,
              backgroundColor: colors.surface,
              borderRadius: radius.lg,
              padding: spacing.md,
            }}>
            <Icon name="information-circle-outline" size={moderateScale(18)} color={colors.textSecondary} />
            <AppText variant="caption" color={colors.textSecondary} style={{marginLeft: spacing.sm, flex: 1}}>
              {sales.bookingProcess ||
                'Meet our sales team at the project site to explore plans, walkthroughs and special offers.'}
            </AppText>
          </View>
        </View>
      )}
    </View>
  );
};

export default ProjectFilesSalesTab;
