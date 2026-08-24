import React, {useState} from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  AppVersion,
  Avatar,
  Badge,
  Button,
  Card,
  ConfirmDialog,
  ScreenContainer,
  SectionHeader,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {deleteAccount, logout} from '../../store/slices/authSlice';
import {showSnackbar} from '../../store/slices/uiSlice';
import {splitSuffix} from '../../utils/name';
import {openLink} from '../../utils/openLink';

const fallback = value => (value && String(value).trim() ? value : '—');
const yesNo = value => (value ? 'Yes' : 'No');

/**
 * `half` pairs two of these side by side (Suffix/Mobile, State/City, and so on) —
 * most values here are a word or two, so stacking every field at full width was
 * mostly empty margin either side of a short answer. Reserved for fields whose
 * value is reliably short; anything that can run long (addresses, URLs, the
 * comma-joined segment/zone lists) stays full width so it has room to wrap.
 */
const InfoRow = ({icon, label, value, valueColor, half}) => {
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
        <AppText variant="bodyMedium" color={valueColor} numberOfLines={half ? 2 : undefined}>
          {value}
        </AppText>
      </View>
    </View>
  );
};

/**
 * A KYC number plus whether its scan was attached, as one row instead of two — the
 * status reads as a quiet trailing glyph rather than a second "Attached"/"Not
 * attached" text row (or a colour-pill, which reads as routine-metadata clutter for
 * something this incidental — a pill is reserved for a real state like an
 * application's Pending/Approved/Rejected).
 *
 * Tapping opens the actual scan (image or PDF) via `openLink` — same "hand off to
 * whatever the device already has" approach `AttachmentList` uses for property
 * documents, rather than shipping an in-app viewer per file type. Only tappable when
 * there's really a file behind it; an unattached document has nothing to open.
 */
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

/** Lays its InfoRow/DocumentRow children out left-to-right, wrapping onto new lines
 *  as `half`/full-width children fill each row — a plain `flexWrap` grid rather than
 *  hand-pairing every field into its own `<View style={{flexDirection:'row'}}>`. */
const FieldGrid = ({children}) => (
  <View style={{flexDirection: 'row', flexWrap: 'wrap'}}>{children}</View>
);

const ProfileScreen = () => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const [confirmLogout, setConfirmLogout] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  // The API returns the user plus their broker_profile; flatten into the shape
  // this screen renders so the field list below stays untouched.
  const user = useAppSelector(state => state.auth.user) ?? {};
  const profile = user.broker_profile ?? {};
  // There is no separate `suffix` column — registration joins it straight into
  // `name` ("Mr. Satya"), so reading it back out is the only way to show it here.
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

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
          Profile
        </AppText>

        <Card>
          <View style={{alignItems: 'center'}}>
            <Avatar
              uri={broker.photoAttachment}
              name={broker.fullNameAsRera}
              size="xl"
              ringColor={colors.primary}
            />
            <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
              {broker.suffix ? `${broker.suffix} ` : ''}
              {broker.fullNameAsRera || 'Broker'}
            </AppText>
            <AppText variant="body" color={colors.textSecondary}>
              {broker.emailId}
            </AppText>
            <View style={{marginTop: spacing.sm}}>
              <Badge label="Verified Partner" tone="success" />
            </View>
          </View>
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={1} title="Personal info" />
        </View>
        <Card>
          <FieldGrid>
            <InfoRow half icon="person-outline" label="Suffix" value={fallback(broker.suffix)} />
            <InfoRow half icon="call-outline" label="Mobile Number" value={fallback(broker.mobileNumber)} />
            <InfoRow half icon="call-outline" label="Alternate Mobile" value={fallback(broker.alternateMobile)} />
            <InfoRow half icon="mail-outline" label="Email ID" value={fallback(broker.emailId)} />
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
          <SectionHeader step={3} title="More Business info" />
        </View>
        <Card>
          <DocumentRow icon="card-outline" label="PAN Card" value={broker.panCard} uri={broker.panCardAttachment} />
          <DocumentRow icon="card-outline" label="Aadhaar Card" value={broker.aadhaarCard} uri={broker.aadhaarAttachment} />
          <DocumentRow icon="shield-checkmark-outline" label="RERA Number" value={broker.reraNumber} uri={broker.reraCertificateAttachment} />
          {/* No cancelled-cheque row: registration stopped collecting the number and
              the scan, so it could only ever render the "—" placeholder. */}
          <DocumentRow icon="receipt-outline" label="GST Number" value={broker.gstNumber} uri={broker.gstAttachment} />
          {/* No signature row: the register form's pad only sets a local `hasSignature`
              flag and never uploads the image, so nothing about it survives submission
              and this always read "Not signed" — including for people who had signed. */}
        </Card>

        <Button
          label="Log Out"
          variant="outline"
          icon="log-out-outline"
          style={{marginTop: spacing.xl}}
          onPress={() => setConfirmLogout(true)}
        />

        <Button
          label="Delete Account"
          variant="danger"
          icon="trash-outline"
          style={{marginTop: spacing.sm}}
          onPress={() => setConfirmDelete(true)}
        />
        <AppVersion />
      </ScrollView>

      <ConfirmDialog
        visible={confirmLogout}
        icon="log-out-outline"
        tone="danger"
        title="Log out?"
        message="You'll need to sign in again to get back to your account."
        confirmLabel="Log out"
        onCancel={() => setConfirmLogout(false)}
        onConfirm={() => {
          setConfirmLogout(false);
          dispatch(logout());
          dispatch(showSnackbar('Signed out'));
        }}
      />

      <ConfirmDialog
        visible={confirmDelete}
        icon="trash-outline"
        tone="danger"
        title="Delete your account?"
        message="This can't be undone. Your profile will be deactivated and you'll be signed out — an admin will see the account as inactive."
        confirmLabel="Delete account"
        busy={isDeleting}
        onCancel={() => setConfirmDelete(false)}
        onConfirm={async () => {
          setIsDeleting(true);
          const result = await dispatch(deleteAccount());
          setIsDeleting(false);
          setConfirmDelete(false);

          if (deleteAccount.fulfilled.match(result)) {
            dispatch(showSnackbar('Your account has been deleted'));
          } else {
            dispatch(
              showSnackbar({
                message: result.payload?.message ?? 'Could not delete your account. Please try again.',
                tone: 'danger',
              }),
            );
          }
        }}
      />
    </ScreenContainer>
  );
};

export default ProfileScreen;
