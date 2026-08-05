import React, {useState} from 'react';
import {ScrollView, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Badge,
  Button,
  Card,
  ConfirmDialog,
  ScreenContainer,
  SectionHeader,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {logout} from '../../store/slices/authSlice';
import {showSnackbar} from '../../store/slices/uiSlice';

const fallback = value => (value && String(value).trim() ? value : '—');
const yesNo = value => (value ? 'Yes' : 'No');
const attachmentLabel = uri => (uri ? 'Attached' : 'Not attached');

const InfoRow = ({icon, label, value, valueColor}) => {
  const {colors, spacing} = useAppTheme();
  return (
    <View style={{flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.sm}}>
      <Icon name={icon} size={moderateScale(18)} color={colors.primary} />
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium" color={valueColor}>
          {value}
        </AppText>
      </View>
    </View>
  );
};

const ProfileScreen = () => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const [confirmLogout, setConfirmLogout] = useState(false);
  // The API returns the user plus their broker_profile; flatten into the shape
  // this screen renders so the field list below stays untouched.
  const user = useAppSelector(state => state.auth.user) ?? {};
  const profile = user.broker_profile ?? {};
  const broker = {
    suffix: '',
    fullNameAsRera: user.name,
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
    aadhaarCard: profile.aadhaar_card,
    reraNumber: profile.rera_number,
    reraCertificateExpiry: profile.rera_certificate_expiry,
    gstNumber: profile.gst_number,
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
              <Badge label="Approved Broker" tone="success" />
            </View>
          </View>
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={1} title="Personal info" />
        </View>
        <Card>
          <InfoRow icon="person-outline" label="Suffix" value={fallback(broker.suffix)} />
          <InfoRow icon="call-outline" label="Mobile Number" value={fallback(broker.mobileNumber)} />
          <InfoRow icon="call-outline" label="Alternate Mobile" value={fallback(broker.alternateMobile)} />
          <InfoRow icon="mail-outline" label="Email ID" value={fallback(broker.emailId)} />
          <InfoRow icon="home-outline" label="Residence Address" value={fallback(broker.residenceAddress)} />
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={2} title="Professional info" />
        </View>
        <Card>
          <InfoRow icon="business-outline" label="Registered as Company" value={yesNo(broker.isCompany)} />
          {broker.isCompany && (
            <>
              <InfoRow icon="business-outline" label="Company Name" value={fallback(broker.companyName)} />
              <InfoRow icon="location-outline" label="Office Address" value={fallback(broker.officeAddress)} />
              <InfoRow icon="globe-outline" label="Company Website" value={fallback(broker.companyWebsite)} />
              <InfoRow icon="logo-instagram" label="Instagram" value={fallback(broker.instagram)} />
              <InfoRow icon="logo-facebook" label="Facebook" value={fallback(broker.facebook)} />
              <InfoRow icon="logo-youtube" label="YouTube" value={fallback(broker.youtube)} />
              <InfoRow icon="logo-twitter" label="Twitter / X" value={fallback(broker.twitter)} />
              <InfoRow icon="logo-linkedin" label="LinkedIn" value={fallback(broker.linkedin)} />
              <InfoRow icon="briefcase-outline" label="Years of Experience" value={fallback(broker.yearsOfExperience)} />
              <InfoRow icon="people-outline" label="Team Size" value={fallback(broker.teamSize)} />
            </>
          )}
          <InfoRow icon="card-outline" label="PAN Card" value={fallback(broker.panCard)} />
          <InfoRow
            icon="document-attach-outline"
            label="PAN Card Attachment"
            value={attachmentLabel(broker.panCardAttachment)}
            valueColor={broker.panCardAttachment ? colors.success : colors.textMuted}
          />
          <InfoRow icon="card-outline" label="Aadhaar Card" value={fallback(broker.aadhaarCard)} />
          <InfoRow
            icon="document-attach-outline"
            label="Aadhaar Attachment"
            value={attachmentLabel(broker.aadhaarAttachment)}
            valueColor={broker.aadhaarAttachment ? colors.success : colors.textMuted}
          />
          <InfoRow icon="shield-checkmark-outline" label="RERA Number" value={fallback(broker.reraNumber)} />
          <InfoRow icon="calendar-outline" label="RERA Certificate Expiry" value={fallback(broker.reraCertificateExpiry)} />
          <InfoRow
            icon="document-attach-outline"
            label="RERA Certificate Attachment"
            value={attachmentLabel(broker.reraCertificateAttachment)}
            valueColor={broker.reraCertificateAttachment ? colors.success : colors.textMuted}
          />
          {/* No cancelled-cheque rows: registration stopped collecting the number and the
              scan, so both could only ever render the "—" placeholder. */}
          <InfoRow icon="receipt-outline" label="GST Number" value={fallback(broker.gstNumber)} />
          <InfoRow
            icon="document-attach-outline"
            label="GST Attachment"
            value={attachmentLabel(broker.gstAttachment)}
            valueColor={broker.gstAttachment ? colors.success : colors.textMuted}
          />
        </Card>

        <View style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
          <SectionHeader step={3} title="Business info" />
        </View>
        <Card>
          <InfoRow icon="location-outline" label="State" value={fallback(broker.state)} />
          <InfoRow icon="location-outline" label="City" value={fallback(broker.city)} />
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
          <InfoRow icon="earth-outline" label="Operates in Multiple States" value={yesNo(broker.operatesMultipleStates)} />
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
    </ScreenContainer>
  );
};

export default ProfileScreen;
