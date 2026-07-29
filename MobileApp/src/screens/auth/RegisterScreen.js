import React, {useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {useAppTheme} from '../../theme';
import {
  AppText,
  AttachBox,
  AttachPill,
  Button,
  Checkbox,
  Dropdown,
  Input,
  ScreenContainer,
  SectionHeader,
  SignaturePad,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {registerBroker} from '../../store/slices/authSlice';

const SUFFIX_OPTIONS = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Eng.'];
const SEGMENT_OPTIONS = ['Residential', 'Commercial', 'Lands', 'Liaisoning', 'All'];
const ZONE_OPTIONS = ['East', 'West', 'North', 'South', 'Central', 'All'];

const initialForm = {
  suffix: '',
  fullNameAsRera: '',
  mobileNumber: '',
  alternateMobile: '',
  emailId: '',
  password: '',
  residenceAddress: '',
  photoAttachment: '',
  isCompany: false,
  companyName: '',
  officeAddress: '',
  sameAsResidenceAddress: false,
  companyWebsite: '',
  socialMediaHandle: '',
  yearsOfExperience: '',
  teamSize: '',
  panCard: '',
  panCardAttachment: '',
  aadhaarCard: '',
  aadhaarAttachment: '',
  reraNumber: '',
  reraCertificateAttachment: '',
  reraCertificateExpiry: '',
  chequeDetails: '',
  chequeAttachment: '',
  gstNumber: '',
  gstAttachment: '',
  state: '',
  city: '',
  segments: [],
  zones: [],
  projectContributions: '',
  operatesMultipleStates: false,
  confirmAccuracy: false,
  hasSignature: false,
};

const RegisterScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const [form, setForm] = useState(initialForm);
  const [errors, setErrors] = useState({});
  const [isScrollEnabled, setIsScrollEnabled] = useState(true);

  const update = key => value => setForm(prev => ({...prev, [key]: value}));

  const toggleCheckbox = key => () =>
    setForm(prev => {
      const next = {...prev, [key]: !prev[key]};
      if (key === 'sameAsResidenceAddress' && next.sameAsResidenceAddress) {
        next.officeAddress = prev.residenceAddress;
      }
      return next;
    });

  const toggleArrayValue = (key, value) => {
    setForm(prev => {
      const arr = prev[key];
      const nextArr = arr.includes(value) ? arr.filter(v => v !== value) : [...arr, value];
      return {...prev, [key]: nextArr};
    });
  };

  const validate = () => {
    const next = {};
    if (!/^\+?[0-9]{7,15}$/.test(form.mobileNumber.trim())) {
      next.mobileNumber = 'Enter a valid mobile number';
    }
    if (form.alternateMobile.trim() && !/^\+?[0-9]{7,15}$/.test(form.alternateMobile.trim())) {
      next.alternateMobile = 'Enter a valid mobile number';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.emailId.trim())) {
      next.emailId = 'Enter a valid email';
    }
    if (!form.password || form.password.length < 8) {
      next.password = 'Password must be at least 8 characters';
    }
    if (!form.residenceAddress.trim()) {
      next.residenceAddress = 'Enter your residence address';
    }
    if (form.isCompany && !form.companyName.trim()) {
      next.companyName = 'Enter company name';
    }
    if (form.isCompany && !form.officeAddress.trim()) {
      next.officeAddress = 'Enter office address';
    }
    if (!form.panCard.trim()) {
      next.panCard = 'Enter PAN card number';
    }
    if (!form.aadhaarCard.trim()) {
      next.aadhaarCard = 'Enter Aadhaar number';
    }
    if (!form.reraNumber.trim()) {
      next.reraNumber = 'Enter RERA number';
    }
    if (!form.reraCertificateExpiry.trim()) {
      next.reraCertificateExpiry = 'Enter certificate expiry date';
    }
    if (!form.chequeDetails.trim()) {
      next.chequeDetails = 'Enter cancelled cheque details';
    }
    if (!form.confirmAccuracy) {
      next.confirmAccuracy = 'Please confirm to continue';
    }
    if (!form.hasSignature) {
      next.signature = 'Please sign to continue';
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  /** Maps the empanelment form onto the API's register contract. */
  const toPayload = () => ({
    name: [form.suffix, form.fullNameAsRera].filter(Boolean).join(' ').trim(),
    email: form.emailId.trim(),
    password: form.password,
    mobile: form.mobileNumber.trim(),

    alternate_mobile: form.alternateMobile.trim() || null,
    residence_address: form.residenceAddress.trim(),
    is_company: form.isCompany,
    company_name: form.companyName.trim() || null,
    office_address: form.officeAddress.trim() || null,
    company_website: form.companyWebsite.trim() || null,
    social_media_handle: form.socialMediaHandle.trim() || null,
    years_of_experience: form.yearsOfExperience ? Number(form.yearsOfExperience) : null,
    team_size: form.teamSize ? Number(form.teamSize) : null,

    pan_card: form.panCard.trim() || null,
    aadhaar_card: form.aadhaarCard.trim() || null,
    rera_number: form.reraNumber.trim() || null,
    rera_certificate_expiry: form.reraCertificateExpiry.trim() || null,
    gst_number: form.gstNumber.trim() || null,
    cheque_details: form.chequeDetails.trim() || null,

    state: form.state.trim() || null,
    city: form.city.trim() || null,
    segments: form.segments,
    zones: form.zones,
    operates_multiple_states: form.operatesMultipleStates,
    project_contributions: form.projectContributions.trim() || null,
    confirm_accuracy: form.confirmAccuracy,
  });

  const handleSubmit = async () => {
    if (!validate()) {
      return;
    }

    const result = await dispatch(registerBroker(toPayload()));

    if (registerBroker.fulfilled.match(result)) {
      navigation.replace('PendingApproval');
      return;
    }

    // Surface server-side validation (e.g. duplicate email) on the right fields.
    const serverErrors = result.payload?.errors ?? {};
    setErrors(prev => ({
      ...prev,
      emailId: serverErrors.email?.[0],
      password: serverErrors.password?.[0],
      mobileNumber: serverErrors.mobile?.[0],
      submit: serverErrors.email ? undefined : result.payload?.message,
    }));
  };

  return (
    <ScreenContainer edges={['top', 'bottom']}>
      <KeyboardAwareScrollView
        showsVerticalScrollIndicator={false}
        enableOnAndroid
        enableResetScrollToCoords={false}
        scrollEnabled={isScrollEnabled}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
          <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
            <Icon name="chevron-back" size={moderateScale(22)} color={colors.textPrimary} />
          </TouchableOpacity>
          <AppText variant="h1" style={{marginLeft: spacing.sm}}>
            Register
          </AppText>
        </View>
        <AppText
          variant="caption"
          color={colors.textSecondary}
          style={{marginTop: spacing.xxs, marginBottom: spacing.xl}}>
          Broker / CP / Agent — empanelment form
        </AppText>

        {/* Step 1 — Personal info */}
        <SectionHeader step={1} title="Personal info" />
        <View style={{marginTop: spacing.md}}>
          <View style={{flexDirection: 'row'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Dropdown
                label="Suffix"
                placeholder="Mr / Mrs / Ms"
                displayValue={form.suffix}
                options={SUFFIX_OPTIONS}
                selected={form.suffix}
                onSelectSingle={update('suffix')}
              />
            </View>
            <View style={{flex: 1.4, marginLeft: spacing.xs}}>
              <Input
                label="Full name (as per RERA)"
                placeholder="e.g. Ravi Teja"
                value={form.fullNameAsRera}
                onChangeText={update('fullNameAsRera')}
              />
            </View>
          </View>

          <Input
            label="Mobile number *"
            placeholder="10-digit mobile"
            leftIcon="call-outline"
            keyboardType="phone-pad"
            value={form.mobileNumber}
            onChangeText={update('mobileNumber')}
            error={errors.mobileNumber}
          />
          <Input
            label="Alternate mobile"
            placeholder="Optional"
            leftIcon="call-outline"
            keyboardType="phone-pad"
            value={form.alternateMobile}
            onChangeText={update('alternateMobile')}
            error={errors.alternateMobile}
          />
          <Input
            label="Email ID *"
            placeholder="you@company.com"
            leftIcon="mail-outline"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            value={form.emailId}
            onChangeText={update('emailId')}
            error={errors.emailId}
          />
          {/* Email + password is the login credential once an admin approves. */}
          <Input
            label="Password *"
            placeholder="At least 8 characters"
            leftIcon="lock-closed-outline"
            isPassword
            value={form.password}
            onChangeText={update('password')}
            error={errors.password}
          />
          <Input
            label="Residence address *"
            placeholder="Flat / street / area / city / pincode"
            leftIcon="home-outline"
            multiline
            value={form.residenceAddress}
            onChangeText={update('residenceAddress')}
            error={errors.residenceAddress}
          />

          <AppText variant="caption" color={colors.textSecondary} style={styles.label}>
            Photo attachment
          </AppText>
          <View style={{marginBottom: spacing.sm}}>
            <AttachBox
              uri={form.photoAttachment}
              onPick={update('photoAttachment')}
              onRemove={() => update('photoAttachment')('')}
              placeholder="Tap to upload a passport-size photo"
            />
          </View>
        </View>

        <View style={[styles.divider, {backgroundColor: colors.border}]} />

        {/* Step 2 — Professional info */}
        <SectionHeader step={2} title="Professional info" />
        <View style={{marginTop: spacing.md}}>
          <Checkbox
            checked={form.isCompany}
            onToggle={toggleCheckbox('isCompany')}
            label="Registering as a Company? (check if yes)"
          />

          {form.isCompany && (
            <View style={{marginTop: spacing.md}}>
              <AppText variant="captionMedium" color={colors.primary} style={styles.label}>
                If yes →
              </AppText>
              <Input
                label="Company name"
                placeholder="Firm name"
                leftIcon="business-outline"
                value={form.companyName}
                onChangeText={update('companyName')}
                error={errors.companyName}
              />
              <Input
                label="Office address *"
                placeholder="Office location"
                leftIcon="location-outline"
                editable={!form.sameAsResidenceAddress}
                value={form.officeAddress}
                onChangeText={update('officeAddress')}
                error={errors.officeAddress}
              />
              <View style={{marginBottom: spacing.md}}>
                <Checkbox
                  checked={form.sameAsResidenceAddress}
                  onToggle={toggleCheckbox('sameAsResidenceAddress')}
                  label="Same as residence address"
                />
              </View>

              <View style={{flexDirection: 'row'}}>
                <View style={{flex: 1, marginRight: spacing.xs}}>
                  <Input
                    label="Company website"
                    placeholder="https://..."
                    autoCapitalize="none"
                    value={form.companyWebsite}
                    onChangeText={update('companyWebsite')}
                  />
                </View>
                <View style={{flex: 1, marginLeft: spacing.xs}}>
                  <Input
                    label="Social media handle"
                    placeholder="Instagram / LinkedIn"
                    autoCapitalize="none"
                    value={form.socialMediaHandle}
                    onChangeText={update('socialMediaHandle')}
                  />
                </View>
              </View>

              <View style={{flexDirection: 'row'}}>
                <View style={{flex: 1, marginRight: spacing.xs}}>
                  <Input
                    label="Total years of experience"
                    placeholder="e.g. 5"
                    keyboardType="numeric"
                    value={form.yearsOfExperience}
                    onChangeText={update('yearsOfExperience')}
                  />
                </View>
                <View style={{flex: 1, marginLeft: spacing.xs}}>
                  <Input
                    label="Team size"
                    placeholder="e.g. 4"
                    keyboardType="numeric"
                    value={form.teamSize}
                    onChangeText={update('teamSize')}
                  />
                </View>
              </View>
            </View>
          )}

          <View style={{flexDirection: 'row', alignItems: 'flex-end'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                label="PAN card *"
                placeholder="ABCDE1234F"
                autoCapitalize="characters"
                value={form.panCard}
                onChangeText={update('panCard')}
                error={errors.panCard}
              />
            </View>
            <View style={{marginBottom: spacing.sm}}>
              <AttachPill
                uri={form.panCardAttachment}
                onPick={update('panCardAttachment')}
                onRemove={() => update('panCardAttachment')('')}
              />
            </View>
          </View>

          <View style={{flexDirection: 'row', alignItems: 'flex-end'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                label="Aadhaar card *"
                placeholder="XXXX XXXX XXXX"
                keyboardType="number-pad"
                value={form.aadhaarCard}
                onChangeText={update('aadhaarCard')}
                error={errors.aadhaarCard}
              />
            </View>
            <View style={{marginBottom: spacing.sm}}>
              <AttachPill
                uri={form.aadhaarAttachment}
                onPick={update('aadhaarAttachment')}
                onRemove={() => update('aadhaarAttachment')('')}
              />
            </View>
          </View>

          <View style={{flexDirection: 'row', alignItems: 'flex-end'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                label="RERA number *"
                placeholder="A02400012345"
                autoCapitalize="characters"
                value={form.reraNumber}
                onChangeText={update('reraNumber')}
                error={errors.reraNumber}
              />
            </View>
            <View style={{marginBottom: spacing.sm}}>
              <AttachPill
                uri={form.reraCertificateAttachment}
                onPick={update('reraCertificateAttachment')}
                onRemove={() => update('reraCertificateAttachment')('')}
              />
            </View>
          </View>

          <Input
            label="RERA certificate validity / expiry date *"
            placeholder="DD/MM/YYYY"
            leftIcon="calendar-outline"
            value={form.reraCertificateExpiry}
            onChangeText={update('reraCertificateExpiry')}
            error={errors.reraCertificateExpiry}
          />

          <View style={{flexDirection: 'row', alignItems: 'flex-end'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                label="Cancelled cheque *"
                placeholder="Account number / IFSC"
                value={form.chequeDetails}
                onChangeText={update('chequeDetails')}
                error={errors.chequeDetails}
              />
            </View>
            <View style={{marginBottom: spacing.sm}}>
              <AttachPill
                uri={form.chequeAttachment}
                onPick={update('chequeAttachment')}
                onRemove={() => update('chequeAttachment')('')}
              />
            </View>
          </View>

          <View style={{flexDirection: 'row', alignItems: 'flex-end'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                label="GST number (if any)"
                placeholder="Optional"
                autoCapitalize="characters"
                value={form.gstNumber}
                onChangeText={update('gstNumber')}
              />
            </View>
            <View style={{marginBottom: spacing.sm}}>
              <AttachPill
                uri={form.gstAttachment}
                onPick={update('gstAttachment')}
                onRemove={() => update('gstAttachment')('')}
              />
            </View>
          </View>
        </View>

        <View style={[styles.divider, {backgroundColor: colors.border}]} />

        {/* Step 3 — Business info */}
        <SectionHeader step={3} title="Business info" />
        <View style={{marginTop: spacing.md}}>
          <View style={{flexDirection: 'row'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input label="State" placeholder="Telangana" value={form.state} onChangeText={update('state')} />
            </View>
            <View style={{flex: 1, marginLeft: spacing.xs}}>
              <Input label="City" placeholder="Hyderabad" value={form.city} onChangeText={update('city')} />
            </View>
          </View>

          <Dropdown
            label="Segment"
            placeholder="Select segment(s)"
            displayValue={form.segments.join(', ')}
            options={SEGMENT_OPTIONS}
            multiSelect
            selected={form.segments}
            onToggleMulti={value => toggleArrayValue('segments', value)}
          />

          <Dropdown
            label="Zone"
            placeholder="Select zone(s)"
            displayValue={form.zones.join(', ')}
            options={ZONE_OPTIONS}
            multiSelect
            selected={form.zones}
            onToggleMulti={value => toggleArrayValue('zones', value)}
          />

          <Input
            label="Project contributions (if any)"
            placeholder="Notable projects you've worked on"
            multiline
            value={form.projectContributions}
            onChangeText={update('projectContributions')}
          />

          <View style={{marginBottom: spacing.md}}>
            <Checkbox
              checked={form.operatesMultipleStates}
              onToggle={toggleCheckbox('operatesMultipleStates')}
              label="Multiple states — operates in more than one state"
            />
          </View>

          <View style={{marginBottom: spacing.lg}}>
            <Checkbox
              checked={form.confirmAccuracy}
              onToggle={toggleCheckbox('confirmAccuracy')}
              label="I confirm the above details are accurate and understand my account needs Admin approval before I can sign in."
              error={errors.confirmAccuracy}
            />
          </View>

          <AppText variant="caption" color={colors.textSecondary} style={styles.label}>
            Authorized signature *
          </AppText>
          <View style={{marginBottom: spacing.lg}}>
            <SignaturePad
              onChange={value => update('hasSignature')(value)}
              onDrawStart={() => setIsScrollEnabled(false)}
              onDrawEnd={() => setIsScrollEnabled(true)}
              error={errors.signature}
            />
          </View>
        </View>

        <Button label="Submit for approval" icon="arrow-forward" iconPosition="right" onPress={handleSubmit} />

        <View style={styles.footerRow}>
          <AppText variant="body" color={colors.textSecondary}>
            Already registered?{' '}
          </AppText>
          <TouchableOpacity onPress={() => navigation.replace('Login')} hitSlop={8}>
            <AppText variant="bodyMedium" color={colors.primary}>
              Log In
            </AppText>
          </TouchableOpacity>
        </View>
      </KeyboardAwareScrollView>
    </ScreenContainer>
  );
};

const styles = StyleSheet.create({
  label: {
    marginBottom: moderateScale(5),
  },
  divider: {
    height: 1,
    marginVertical: moderateScale(24),
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: moderateScale(20),
  },
});

export default RegisterScreen;
