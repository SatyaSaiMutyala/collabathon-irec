import React, {useState} from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Button, Card, SectionHeader, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {reopenRegistration} from '../../store/slices/authSlice';
import {showSnackbar} from '../../store/slices/uiSlice';
import {splitSuffix} from '../../utils/name';
import {openLink} from '../../utils/openLink';

const fallback = value => (value && String(value).trim() ? value : '—');
const yesNo = value => (value ? 'Yes' : 'No');

/** Same field-row language as the active broker's own Profile screen — not shared
 *  from there (it isn't exported), same reasoning DocumentAttachBox's local
 *  ImageViewer duplicates AttachField's rather than pulling in a whole module for
 *  two small components. */
const InfoRow = ({icon, label, value, half}) => {
  const {colors, spacing} = useAppTheme();
  return (
    <View
      style={{
        width: half ? '50%' : '100%',
        flexDirection: 'row',
        alignItems: 'flex-start',
        paddingVertical: spacing.xs,
        paddingRight: half ? spacing.sm : 0,
      }}>
      <Icon name={icon} size={moderateScale(15)} color={colors.primary} style={{marginTop: moderateScale(2)}} />
      <View style={{marginLeft: spacing.xs, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium" numberOfLines={half ? 2 : undefined}>
          {value}
        </AppText>
      </View>
    </View>
  );
};

/** A KYC number plus whether its scan was attached, tappable to open it — see the
 *  identically-named row on the active broker's own Profile screen. */
const DocumentRow = ({icon, label, value, uri}) => {
  const {colors, radius, spacing} = useAppTheme();
  const attached = !!uri;

  return (
    <TouchableOpacity
      activeOpacity={attached ? 0.7 : 1}
      disabled={!attached}
      onPress={() => openLink(uri)}
      style={{width: '100%', flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.xs}}>
      <View
        style={{
          width: moderateScale(32),
          height: moderateScale(32),
          borderRadius: radius.sm,
          backgroundColor: colors.primarySoft,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <Icon name={icon} size={moderateScale(16)} color={colors.primaryDark} />
      </View>
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium">{fallback(value)}</AppText>
      </View>
      {attached && <Icon name="open-outline" size={moderateScale(17)} color={colors.textMuted} />}
    </TouchableOpacity>
  );
};

const FieldGrid = ({children}) => (
  <View style={{flexDirection: 'row', flexWrap: 'wrap'}}>{children}</View>
);

/**
 * A read-only look at everything submitted, reached by tapping the summary card on
 * PendingApprovalScreen — that card used to be inert; a broker waiting on a decision
 * had no way to actually see what they'd sent in. Same field set and layout as the
 * active broker's own Profile screen (this *is* that data, just before approval),
 * minus the account-management actions that only make sense once approved.
 *
 * `user.broker_profile` — not `draftProfile` — is what this reads: unlike
 * `draft_profile` (UserResource's own field, gated to `status === draft`),
 * `broker_profile` is unconditional on the relation just being loaded, which it
 * already is the instant step 3 finalizes to `pending` and again on every
 * `/auth/me` — so this works with no backend change at all.
 */
const RegistrationPreviewScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const [isReopening, setIsReopening] = useState(false);
  const user = useAppSelector(state => state.auth.user) ?? {};
  const profile = user.broker_profile ?? {};
  const {suffix, rest: fullNameAsRera} = splitSuffix(user.name);

  const broker = {
    suffix,
    fullNameAsRera,
    mobileNumber: user.mobile,
    emailId: user.email,
    photoAttachment: user.avatar_url,
    alternateMobile: profile.alternate_mobile,
    residenceAddress: profile.residence_address,
    isCompany: profile.is_company,
    companyName: profile.company_name,
    officeAddress: profile.office_address,
    companyWebsite: profile.company_website,
    instagram: profile.instagram,
    facebook: profile.facebook,
    youtube: profile.youtube,
    twitter: profile.twitter,
    linkedin: profile.linkedin,
    yearsOfExperience: profile.years_of_experience,
    teamSize: profile.team_size,
    panCard: profile.pan_card,
    panCardAttachment: profile.pan_card_path,
    aadhaarCard: profile.aadhaar_card,
    aadhaarAttachment: profile.aadhaar_path,
    reraNumber: profile.rera_number,
    reraCertificateAttachment: profile.rera_certificate_path,
    gstNumber: profile.gst_number,
    gstAttachment: profile.gst_path,
    state: profile.state,
    city: profile.city,
    segments: profile.segments,
    zones: profile.zones,
    projectContributions: profile.project_contributions,
    operatesMultipleStates: profile.operates_multiple_states,
  };

  // Same reopenRegistration -> CompleteProfile(step 1) flow as PendingApprovalScreen's
  // own button — duplicated rather than lifted into a shared hook for two call sites
  // this small.
  const handleUpdateProfile = async () => {
    setIsReopening(true);
    const result = await dispatch(reopenRegistration());
    setIsReopening(false);

    if (reopenRegistration.fulfilled.match(result)) {
      navigation.replace('CompleteProfile', {startAtStep: 1});
      return;
    }

    dispatch(
      showSnackbar({
        message: result.payload?.message ?? 'Could not reopen your registration. Please try again.',
        tone: 'danger',
      }),
    );
  };

  return (
    <ScreenContainer edges={['top', 'bottom']}>
      <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm, marginBottom: spacing.sm}}>
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={() => navigation.goBack()}
          hitSlop={10}
          style={{marginRight: spacing.xs}}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h2">Profile Preview</AppText>
      </View>

      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <Card>
          <View style={{alignItems: 'center'}}>
            <Avatar uri={broker.photoAttachment} name={broker.fullNameAsRera} size="xl" ringColor={colors.primary} />
            <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
              {broker.suffix ? `${broker.suffix} ` : ''}
              {broker.fullNameAsRera || 'Channel Partner'}
            </AppText>
            <AppText variant="body" color={colors.textSecondary}>
              {broker.emailId}
            </AppText>
            <View style={{marginTop: spacing.sm}}>
              <Badge label="Pending Approval" tone="warning" />
            </View>
          </View>
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={1} title="Personal info" />
        </View>
        <Card>
          <FieldGrid>
            <InfoRow half icon="person-outline" label="Suffix" value={fallback(broker.suffix)} />
            <InfoRow half icon="person-outline" label="Name" value={fallback(broker.fullNameAsRera)} />
            <InfoRow half icon="call-outline" label="Mobile Number" value={fallback(broker.mobileNumber)} />
            <InfoRow half icon="call-outline" label="Alternate Mobile" value={fallback(broker.alternateMobile)} />
            <InfoRow icon="mail-outline" label="Email ID" value={fallback(broker.emailId)} />
            <InfoRow icon="home-outline" label="Residence Address" value={fallback(broker.residenceAddress)} />
          </FieldGrid>
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={2} title="Professional info" />
        </View>
        <Card>
          <FieldGrid>
            <InfoRow half icon="business-outline" label="Registered as Company" value={yesNo(broker.isCompany)} />
            {broker.isCompany && (
              <>
                <InfoRow half icon="business-outline" label="Company Name" value={fallback(broker.companyName)} />
                <InfoRow icon="location-outline" label="Office Address" value={fallback(broker.officeAddress)} />
                <InfoRow icon="globe-outline" label="Company Website" value={fallback(broker.companyWebsite)} />
                <InfoRow half icon="logo-instagram" label="Instagram" value={fallback(broker.instagram)} />
                <InfoRow half icon="logo-facebook" label="Facebook" value={fallback(broker.facebook)} />
                <InfoRow half icon="logo-youtube" label="YouTube" value={fallback(broker.youtube)} />
                <InfoRow half icon="logo-twitter" label="Twitter / X" value={fallback(broker.twitter)} />
                <InfoRow icon="logo-linkedin" label="LinkedIn" value={fallback(broker.linkedin)} />
                <InfoRow half icon="briefcase-outline" label="Years of Experience" value={fallback(broker.yearsOfExperience)} />
                <InfoRow half icon="people-outline" label="Team Size" value={fallback(broker.teamSize)} />
              </>
            )}
            <InfoRow half icon="location-outline" label="State" value={fallback(broker.state)} />
            <InfoRow half icon="location-outline" label="City" value={fallback(broker.city)} />
            <InfoRow
              icon="pricetags-outline"
              label="Segment"
              value={broker.segments?.length ? broker.segments.join(', ') : '—'}
            />
            <InfoRow
              icon="compass-outline"
              label="Zone"
              value={broker.zones?.length ? broker.zones.join(', ') : '—'}
            />
            <InfoRow icon="albums-outline" label="Project Contributions" value={fallback(broker.projectContributions)} />
            <InfoRow half icon="earth-outline" label="Operates in Multiple States" value={yesNo(broker.operatesMultipleStates)} />
          </FieldGrid>
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={3} title="Business info" />
        </View>
        <Card>
          <DocumentRow icon="card-outline" label="PAN Card" value={broker.panCard} uri={broker.panCardAttachment} />
          <DocumentRow icon="card-outline" label="Aadhaar Card" value={broker.aadhaarCard} uri={broker.aadhaarAttachment} />
          <DocumentRow icon="shield-checkmark-outline" label="RERA Number" value={broker.reraNumber} uri={broker.reraCertificateAttachment} />
          <DocumentRow icon="receipt-outline" label="GST Number" value={broker.gstNumber} uri={broker.gstAttachment} />
        </Card>

        <Button
          label="Update Profile"
          icon="create-outline"
          loading={isReopening}
          onPress={handleUpdateProfile}
          style={{marginTop: spacing.xl}}
        />
      </ScrollView>
    </ScreenContainer>
  );
};

export default RegistrationPreviewScreen;
