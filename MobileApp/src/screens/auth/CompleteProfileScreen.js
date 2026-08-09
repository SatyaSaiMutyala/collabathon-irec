import React, {useRef, useState} from 'react';
import {Linking, StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {useAppTheme} from '../../theme';
import {
  AppText,
  AttachBox,
  Button,
  Checkbox,
  DateField,
  Dropdown,
  Input,
  ScreenContainer,
  SectionHeader,
  SignaturePad,
  toApiDate,
} from '../../components';
import {useAppDispatch} from '../../store/hooks';
import {registerBroker} from '../../store/slices/authSlice';
import {showSnackbar} from '../../store/slices/uiSlice';

// TODO: placeholder — swap for the real hosted terms & conditions page.
const TERMS_URL = 'https://collabathon.app/terms';

const SUFFIX_OPTIONS = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Eng.'];
const SEGMENT_OPTIONS = [
  'Residential',
  'Commercial',
  'Lands',
  'Liaisoning',
  'All',
];
const ZONE_OPTIONS = ['East', 'West', 'North', 'South', 'Central', 'All'];

/** Picking "All" covers every other option, so the list has nothing left to offer. */
const TERMINAL_OPTIONS = ['All'];

/** A RERA certificate that has already lapsed cannot be the one being empanelled against. */
const TODAY = new Date();
const MAX_EXPIRY = new Date(
  TODAY.getFullYear() + 30,
  TODAY.getMonth(),
  TODAY.getDate(),
);

/**
 * Every field the API can reject, mapped to the form field that shows it.
 *
 * `verification_token` has no field of its own — the mobile number it protects is
 * locked, read-only, display-only on this screen — so a rejection there (the OTP
 * verification aged out while the form was being filled in) surfaces as the general
 * submit error instead of pointing at a field there is nothing to fix on.
 */
const SERVER_FIELD_TO_FORM = {
  name: 'fullNameAsRera',
  email: 'emailId',
  alternate_mobile: 'alternateMobile',
  residence_address: 'residenceAddress',
  company_name: 'companyName',
  office_address: 'officeAddress',
  company_website: 'companyWebsite',
  instagram: 'instagram',
  facebook: 'facebook',
  youtube: 'youtube',
  twitter: 'twitter',
  linkedin: 'linkedin',
  years_of_experience: 'yearsOfExperience',
  team_size: 'teamSize',
  pan_card: 'panCard',
  aadhaar_card: 'aadhaarCard',
  rera_number: 'reraNumber',
  rera_certificate_expiry: 'reraCertificateExpiry',
  gst_number: 'gstNumber',
  state: 'state',
  city: 'city',
  segments: 'segments',
  zones: 'zones',
  project_contributions: 'projectContributions',
  confirm_accuracy: 'confirmAccuracy',
  photo: 'photoAttachment',
};

/**
 * Fields in the order they appear on screen. Submitting jumps to the first one that
 * failed, so the form is never silently refusing at a field three screens up.
 *
 * `focusVia` is for the controls that cannot take keyboard focus — an attachment box, a
 * calendar field, a checkbox — and names the nearest text input above them, which lands
 * the user in the right part of the form rather than nowhere. `mobileNumber` is not in
 * this list: it is locked, so it can never be the field a failed submit points at.
 */
const FIELD_ORDER = [
  {key: 'fullNameAsRera'},
  {key: 'alternateMobile'},
  {key: 'emailId'},
  {key: 'residenceAddress'},
  {key: 'photoAttachment', focusVia: 'residenceAddress'},
  {key: 'companyName'},
  {key: 'officeAddress'},
  {key: 'companyWebsite'},
  {key: 'instagram'},
  {key: 'facebook'},
  {key: 'youtube'},
  {key: 'twitter'},
  {key: 'linkedin'},
  {key: 'yearsOfExperience'},
  {key: 'teamSize'},
  {key: 'panCard'},
  {key: 'panCardAttachment', focusVia: 'panCard'},
  {key: 'aadhaarCard'},
  {key: 'aadhaarAttachment', focusVia: 'aadhaarCard'},
  {key: 'reraNumber'},
  {key: 'reraCertificateExpiry', focusVia: 'reraNumber'},
  {key: 'reraCertificateAttachment', focusVia: 'reraNumber'},
  {key: 'gstNumber'},
  {key: 'state'},
  {key: 'city'},
  {key: 'segments', focusVia: 'city'},
  {key: 'zones', focusVia: 'city'},
  {key: 'projectContributions'},
  {key: 'confirmAccuracy'},
  {key: 'signature'},
];

const buildInitialForm = mobile => ({
  suffix: '',
  fullNameAsRera: '',
  mobileNumber: mobile ?? '',
  alternateMobile: '',
  emailId: '',
  residenceAddress: '',
  photoAttachment: '',
  isCompany: false,
  companyName: '',
  officeAddress: '',
  sameAsResidenceAddress: false,
  companyWebsite: '',
  instagram: '',
  facebook: '',
  youtube: '',
  twitter: '',
  linkedin: '',
  yearsOfExperience: '',
  teamSize: '',
  panCard: '',
  panCardAttachment: '',
  aadhaarCard: '',
  aadhaarAttachment: '',
  reraNumber: '',
  reraCertificateAttachment: '',
  // A Date, not a typed string: the calendar cannot produce an unparseable value.
  reraCertificateExpiry: null,
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
});

/**
 * The channel-partner empanelment form — reached only after OtpVerifyScreen has
 * already confirmed the mobile number and found no account for it (see
 * AuthController::verifyOtp's `status: 'register'` branch). What used to be a
 * password field here is gone: a broker account has no password at all any more,
 * mobile + OTP is the only sign-in path, and this screen submits the
 * `verification_token` that proves the number was actually checked instead.
 */
const CompleteProfileScreen = ({navigation, route}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const mobile = route.params?.mobile;
  const verificationToken = route.params?.verificationToken;

  const [form, setForm] = useState(() => buildInitialForm(mobile));
  const [errors, setErrors] = useState({});
  const [isScrollEnabled, setIsScrollEnabled] = useState(true);

  // One ref per text field, so a failed submit can put the cursor in the offending one.
  // Focusing is also what scrolls it into view — KeyboardAwareScrollView follows focus.
  const inputRefs = useRef({});
  const registerRef = key => node => {
    inputRefs.current[key] = node;
  };

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
      const nextArr = arr.includes(value)
        ? arr.filter(v => v !== value)
        : [...arr, value];
      return {...prev, [key]: nextArr};
    });
  };

  const validate = () => {
    const next = {};
    if (
      form.alternateMobile.trim() &&
      !/^\+?[0-9]{7,15}$/.test(form.alternateMobile.trim())
    ) {
      next.alternateMobile = 'Enter a valid mobile number';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.emailId.trim())) {
      next.emailId = 'Enter a valid email';
    }
    if (!form.residenceAddress.trim()) {
      next.residenceAddress = 'Enter your address of communication';
    }
    if (!form.photoAttachment) {
      next.photoAttachment = 'Attach a profile photo';
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
    if (!form.panCardAttachment) {
      next.panCardAttachment = 'Attach a copy of the PAN card';
    }
    if (!form.aadhaarCard.trim()) {
      next.aadhaarCard = 'Enter Aadhaar number';
    }
    if (!form.aadhaarAttachment) {
      next.aadhaarAttachment = 'Attach a copy of the Aadhaar card';
    }
    if (!form.reraNumber.trim()) {
      next.reraNumber = 'Enter RERA number';
    }
    if (!form.reraCertificateExpiry) {
      next.reraCertificateExpiry = 'Select the RERA certificate expiry date';
    }
    if (!form.reraCertificateAttachment) {
      next.reraCertificateAttachment = 'Attach the RERA certificate';
    }
    if (!form.confirmAccuracy) {
      next.confirmAccuracy = 'Please confirm to continue';
    }
    if (!form.hasSignature) {
      next.signature = 'Please sign to continue';
    }
    setErrors(next);
    return next;
  };

  /**
   * Takes the user to the first thing that is wrong and says what it is.
   *
   * This is the whole reason the form "did not submit": validation was refusing, but the
   * only sign of it was a red line under a field that could be several screens away from
   * the button. Pressing Submit looked like nothing happened at all.
   */
  const reportErrors = (fieldErrors, fallbackMessage) => {
    const failed = FIELD_ORDER.filter(field => fieldErrors[field.key]);
    const first = failed[0];
    const message =
      (first && fieldErrors[first.key]) ||
      fallbackMessage ||
      'Please check the form and try again.';
    const remaining = Math.max(failed.length - (first ? 1 : 0), 0);

    dispatch(
      showSnackbar({
        message:
          remaining > 0 ? `${message} · ${remaining} more to fix` : message,
        tone: 'danger',
      }),
    );

    const focusKey =
      first && (inputRefs.current[first.key] ? first.key : first.focusVia);
    inputRefs.current[focusKey]?.focus?.();
  };

  /**
   * A picked file as the {uri, name, type} part React Native's fetch understands.
   *
   * `type` is declared as JPEG because the picker only ever returns camera/gallery
   * images here; the server sniffs the real mime anyway and accepts PDF too, so a
   * mislabelled part is validated on its contents rather than this hint.
   */
  const filePart = (uri, fallbackName) =>
    uri
      ? {uri, name: uri.split('/').pop() || fallbackName, type: 'image/jpeg'}
      : null;

  /** Maps the empanelment form onto the API's register contract. */
  const toPayload = () => ({
    verification_token: verificationToken,
    name: [form.suffix, form.fullNameAsRera].filter(Boolean).join(' ').trim(),
    email: form.emailId.trim(),

    alternate_mobile: form.alternateMobile.trim() || null,
    residence_address: form.residenceAddress.trim(),
    is_company: form.isCompany,
    company_name: form.companyName.trim() || null,
    office_address: form.officeAddress.trim() || null,
    company_website: form.companyWebsite.trim() || null,
    instagram: form.instagram.trim() || null,
    facebook: form.facebook.trim() || null,
    youtube: form.youtube.trim() || null,
    twitter: form.twitter.trim() || null,
    linkedin: form.linkedin.trim() || null,
    years_of_experience: form.yearsOfExperience
      ? Number(form.yearsOfExperience)
      : null,
    team_size: form.teamSize ? Number(form.teamSize) : null,

    pan_card: form.panCard.trim() || null,
    aadhaar_card: form.aadhaarCard.trim() || null,
    rera_number: form.reraNumber.trim() || null,
    rera_certificate_expiry: form.reraCertificateExpiry
      ? toApiDate(form.reraCertificateExpiry)
      : null,
    gst_number: form.gstNumber.trim() || null,

    state: form.state.trim() || null,
    city: form.city.trim() || null,
    segments: form.segments,
    zones: form.zones,
    operates_multiple_states: form.operatesMultipleStates,
    project_contributions: form.projectContributions.trim() || null,
    confirm_accuracy: form.confirmAccuracy,

    // The picker hands back a local file:// URI. The transport layer turns this into a
    // multipart part; sending the URI as a plain string would store a path that only
    // ever resolved on the device that typed it.
    photo: filePart(form.photoAttachment, 'photo.jpg'),

    // The KYC scans. The form has always collected these — PAN, Aadhaar and RERA are
    // required to get past validation — but they were never put in the payload, so every
    // registration arrived with the numbers and none of the documents, and the admin's
    // Documents panel read "Not provided" for all of them.
    pan_card_file: filePart(form.panCardAttachment, 'pan-card.jpg'),
    aadhaar_file: filePart(form.aadhaarAttachment, 'aadhaar.jpg'),
    rera_certificate_file: filePart(form.reraCertificateAttachment, 'rera-certificate.jpg'),
    gst_file: filePart(form.gstAttachment, 'gst.jpg'),
  });

  const handleSubmit = async () => {
    const localErrors = validate();
    if (Object.keys(localErrors).length > 0) {
      reportErrors(localErrors);
      return;
    }

    const result = await dispatch(registerBroker(toPayload()));

    if (registerBroker.fulfilled.match(result)) {
      dispatch(
        showSnackbar({
          message:
            result.payload?.message ?? 'Registration submitted for approval.',
          tone: 'success',
        }),
      );
      navigation.replace('PendingApproval');
      return;
    }

    // Every field the server rejected, painted on the field that owns it — the message
    // is the server's own wording, so a rule that changes in Laravel changes here too
    // without the app having to be taught about it. `verification_token` has no field
    // of its own (see SERVER_FIELD_TO_FORM's docblock), so it becomes the submit error.
    const serverErrors = result.payload?.errors ?? {};
    const mapped = {};
    let submitMessage;

    Object.entries(serverErrors).forEach(([apiField, messages]) => {
      if (apiField === 'verification_token') {
        submitMessage = messages?.[0];
        return;
      }
      const formField = SERVER_FIELD_TO_FORM[apiField];
      if (formField) {
        mapped[formField] = messages?.[0];
      }
    });

    const message = result.payload?.message;
    setErrors({
      ...mapped,
      submit: submitMessage ?? (Object.keys(mapped).length === 0 ? message : undefined),
    });
    reportErrors(mapped, submitMessage ?? message);
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
        <View
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            marginTop: spacing.sm,
          }}>
          <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
            <Icon
              name="chevron-back"
              size={moderateScale(22)}
              color={colors.textPrimary}
            />
          </TouchableOpacity>
          <AppText variant="h1" style={{marginLeft: spacing.sm}}>
            Complete your profile
          </AppText>
        </View>
        <AppText
          variant="caption"
          color={colors.textSecondary}
          style={{
            // Aligned under the title, not the back chevron — matches the icon's
            // width plus the gap before the title in the row above.
            marginLeft: moderateScale(22) + spacing.sm,
            marginTop: spacing.xxs,
            marginBottom: spacing.xl,
          }}>
          {mobile ? `${mobile} is verified — ` : ''}a few more details and you're
          ready for admin review.
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
                ref={registerRef('fullNameAsRera')}
                label="Full name (as per RERA)"
                placeholder="e.g. Ravi Teja"
                value={form.fullNameAsRera}
                onChangeText={update('fullNameAsRera')}
              />
            </View>
          </View>

          {/* Locked, not just pre-filled: this is the number OtpVerifyScreen already
              confirmed belongs to whoever is filling in this form — letting it be
              edited here would submit a profile for a number nobody proved they hold. */}
          <Input
            label="Mobile number"
            leftIcon="call-outline"
            value={form.mobileNumber}
            editable={false}
          />
          <Input
            ref={registerRef('alternateMobile')}
            label="Alternate mobile"
            placeholder="Optional"
            leftIcon="call-outline"
            keyboardType="phone-pad"
            value={form.alternateMobile}
            onChangeText={update('alternateMobile')}
            error={errors.alternateMobile}
          />
          <Input
            ref={registerRef('emailId')}
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
          <Input
            ref={registerRef('residenceAddress')}
            label="Address of communication *"
            placeholder="Flat / street / area / city / pincode"
            leftIcon="home-outline"
            multiline
            value={form.residenceAddress}
            onChangeText={update('residenceAddress')}
            error={errors.residenceAddress}
          />

          <AppText
            variant="caption"
            color={colors.textSecondary}
            style={styles.label}>
            Take a photo
          </AppText>
          <View style={{marginBottom: spacing.sm}}>
            <AttachBox
              uri={form.photoAttachment}
              onPick={update('photoAttachment')}
              onRemove={() => update('photoAttachment')('')}
              // This is the broker's profile picture, not a document scan: it is shown
              // in square avatars all over the app, so it is framed square here rather
              // than centre-cropped later on whatever the user happened to upload.
              crop
              label="Profile photo"
              placeholder="Tap to upload a passport-size photo"
              error={errors.photoAttachment}
            />
          </View>
        </View>

        <View style={[styles.divider, {backgroundColor: colors.border}]} />

        {/* Step 2 — Professional info */}
        <SectionHeader step={2} title="Professional info" />
        <View style={{marginTop: spacing.md}}>
          <View style={{marginBottom: spacing.sm}}>
            <Checkbox
              checked={form.isCompany}
              onToggle={toggleCheckbox('isCompany')}
              label="Registering as a Company? (check if yes)"
            />
          </View>

          {form.isCompany && (
            <View style={{marginTop: spacing.md}}>
              <AppText
                variant="captionMedium"
                color={colors.primary}
                style={styles.label}>
                If yes →
              </AppText>
              <Input
                ref={registerRef('companyName')}
                label="Company name"
                placeholder="Firm name"
                leftIcon="business-outline"
                value={form.companyName}
                onChangeText={update('companyName')}
                error={errors.companyName}
              />
              <Input
                ref={registerRef('officeAddress')}
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
                  label="Same as address of communication"
                />
              </View>

              <Input
                ref={registerRef('companyWebsite')}
                label="Company website"
                placeholder="https://..."
                autoCapitalize="none"
                value={form.companyWebsite}
                onChangeText={update('companyWebsite')}
              />

              {/* One field per platform rather than a single "social media handle" box —
                  a broker can run more than one channel, and a named field is what lets
                  the profile screens render each as its own labelled link. */}
              <AppText variant="captionMedium" color={colors.textSecondary} style={styles.label}>
                Social media
              </AppText>
              <View style={{flexDirection: 'row'}}>
                <View style={{flex: 1, marginRight: spacing.xs}}>
                  <Input
                    ref={registerRef('instagram')}
                    label="Instagram"
                    placeholder="@handle or URL"
                    autoCapitalize="none"
                    value={form.instagram}
                    onChangeText={update('instagram')}
                  />
                </View>
                <View style={{flex: 1, marginLeft: spacing.xs}}>
                  <Input
                    ref={registerRef('facebook')}
                    label="Facebook"
                    placeholder="@handle or URL"
                    autoCapitalize="none"
                    value={form.facebook}
                    onChangeText={update('facebook')}
                  />
                </View>
              </View>
              <View style={{flexDirection: 'row'}}>
                <View style={{flex: 1, marginRight: spacing.xs}}>
                  <Input
                    ref={registerRef('youtube')}
                    label="YouTube"
                    placeholder="@handle or URL"
                    autoCapitalize="none"
                    value={form.youtube}
                    onChangeText={update('youtube')}
                  />
                </View>
                <View style={{flex: 1, marginLeft: spacing.xs}}>
                  <Input
                    ref={registerRef('twitter')}
                    label="Twitter / X"
                    placeholder="@handle or URL"
                    autoCapitalize="none"
                    value={form.twitter}
                    onChangeText={update('twitter')}
                  />
                </View>
              </View>
              <Input
                ref={registerRef('linkedin')}
                label="LinkedIn"
                placeholder="@handle or URL"
                autoCapitalize="none"
                value={form.linkedin}
                onChangeText={update('linkedin')}
              />

              <View style={{flexDirection: 'row'}}>
                <View style={{flex: 1, marginRight: spacing.xs}}>
                  <Input
                    ref={registerRef('yearsOfExperience')}
                    label="Total years of experience"
                    placeholder="e.g. 5"
                    keyboardType="numeric"
                    value={form.yearsOfExperience}
                    onChangeText={update('yearsOfExperience')}
                  />
                </View>
                <View style={{flex: 1, marginLeft: spacing.xs}}>
                  <Input
                    ref={registerRef('teamSize')}
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

          <Input
            ref={registerRef('panCard')}
            label="PAN card *"
            placeholder="ABCDE1234F"
            autoCapitalize="characters"
            value={form.panCard}
            onChangeText={update('panCard')}
            error={errors.panCard}
          />
          <View style={{marginBottom: spacing.sm}}>
            <AttachBox
              uri={form.panCardAttachment}
              onPick={update('panCardAttachment')}
              onRemove={() => update('panCardAttachment')('')}
              label="PAN card"
              placeholder="Attach a photo of your PAN card"
              height={120}
              error={errors.panCardAttachment}
            />
          </View>

          <Input
            ref={registerRef('aadhaarCard')}
            label="Aadhaar card *"
            placeholder="XXXX XXXX XXXX"
            keyboardType="number-pad"
            value={form.aadhaarCard}
            onChangeText={update('aadhaarCard')}
            error={errors.aadhaarCard}
          />
          <View style={{marginBottom: spacing.sm}}>
            <AttachBox
              uri={form.aadhaarAttachment}
              onPick={update('aadhaarAttachment')}
              onRemove={() => update('aadhaarAttachment')('')}
              label="Aadhaar card"
              placeholder="Attach a photo of your Aadhaar"
              height={120}
              error={errors.aadhaarAttachment}
            />
          </View>

          <Input
            ref={registerRef('reraNumber')}
            label="RERA number *"
            placeholder="A02400012345"
            autoCapitalize="characters"
            value={form.reraNumber}
            onChangeText={update('reraNumber')}
            error={errors.reraNumber}
          />
          <View style={{marginBottom: spacing.sm}}>
            <AttachBox
              uri={form.reraCertificateAttachment}
              onPick={update('reraCertificateAttachment')}
              onRemove={() => update('reraCertificateAttachment')('')}
              label="RERA certificate"
              placeholder="Attach your RERA certificate"
              height={120}
              error={errors.reraCertificateAttachment}
            />
          </View>

          <DateField
            label="RERA certificate validity / expiry date *"
            placeholder="Tap to pick a date"
            value={form.reraCertificateExpiry}
            onChange={update('reraCertificateExpiry')}
            // A certificate that expired yesterday is not one to empanel against, so the
            // calendar simply cannot reach those days.
            minimumDate={TODAY}
            maximumDate={MAX_EXPIRY}
            error={errors.reraCertificateExpiry}
          />

          <Input
            ref={registerRef('gstNumber')}
            label="GST number (if any)"
            placeholder="Optional"
            autoCapitalize="characters"
            value={form.gstNumber}
            onChangeText={update('gstNumber')}
          />
          <View style={{marginBottom: spacing.sm}}>
            <AttachBox
              uri={form.gstAttachment}
              onPick={update('gstAttachment')}
              onRemove={() => update('gstAttachment')('')}
              label="GST certificate"
              placeholder="Attach your GST certificate"
              height={120}
            />
          </View>
        </View>

        <View style={[styles.divider, {backgroundColor: colors.border}]} />

        {/* Step 3 — More business info */}
        <SectionHeader step={3} title="More business info" />
        <View style={{marginTop: spacing.md}}>
          <View style={{flexDirection: 'row'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                ref={registerRef('state')}
                label="State"
                placeholder="Telangana"
                value={form.state}
                onChangeText={update('state')}
              />
            </View>
            <View style={{flex: 1, marginLeft: spacing.xs}}>
              <Input
                ref={registerRef('city')}
                label="City"
                placeholder="Hyderabad"
                value={form.city}
                onChangeText={update('city')}
              />
            </View>
          </View>

          <Dropdown
            label="Segment"
            placeholder="Select segment(s)"
            displayValue={form.segments.join(', ')}
            options={SEGMENT_OPTIONS}
            multiSelect
            selected={form.segments}
            terminalOptions={TERMINAL_OPTIONS}
            onToggleMulti={value => toggleArrayValue('segments', value)}
          />

          <Dropdown
            label="Zone"
            placeholder="Select zone(s)"
            displayValue={form.zones.join(', ')}
            options={ZONE_OPTIONS}
            multiSelect
            selected={form.zones}
            terminalOptions={TERMINAL_OPTIONS}
            onToggleMulti={value => toggleArrayValue('zones', value)}
          />

          <Input
            ref={registerRef('projectContributions')}
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
              label="Operate more than 1 state"
            />
          </View>

          <View style={{marginBottom: spacing.lg}}>
            <Checkbox
              checked={form.confirmAccuracy}
              onToggle={toggleCheckbox('confirmAccuracy')}
              label="I agree to "
              linkLabel="terms and condition"
              onLinkPress={() => Linking.openURL(TERMS_URL)}
              error={errors.confirmAccuracy}
            />
          </View>

          <AppText
            variant="caption"
            color={colors.textSecondary}
            style={styles.label}>
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

        {errors.submit && (
          <AppText
            variant="caption"
            color={colors.danger}
            style={{textAlign: 'center', marginBottom: spacing.sm}}>
            {errors.submit}
          </AppText>
        )}
        <Button
          label="Submit for approval"
          icon="arrow-forward"
          iconPosition="right"
          onPress={handleSubmit}
        />

        <View style={styles.footerRow}>
          <AppText variant="body" color={colors.textSecondary}>
            Wrong number?{' '}
          </AppText>
          <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={8}>
            <AppText variant="bodyMedium" color={colors.primary}>
              Start over
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

export default CompleteProfileScreen;
