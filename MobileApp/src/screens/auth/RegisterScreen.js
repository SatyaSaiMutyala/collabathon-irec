import React, {useRef, useState} from 'react';
import {ActivityIndicator, Alert, StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import DocumentPicker, {isCancel as isDocumentPickCancelled} from 'react-native-document-picker';
import {moderateScale} from '../../theme/scaling';
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
import {kycApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {registerBroker} from '../../store/slices/authSlice';
import {showSnackbar} from '../../store/slices/uiSlice';

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
 * Previously only email, password and mobile were mapped, so a 422 on any of the other
 * twenty fields painted no error anywhere — the request failed and the form looked idle.
 */
const SERVER_FIELD_TO_FORM = {
  name: 'fullNameAsRera',
  email: 'emailId',
  password: 'password',
  mobile: 'mobileNumber',
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
 * the user in the right part of the form rather than nowhere.
 */
const FIELD_ORDER = [
  {key: 'fullNameAsRera'},
  {key: 'mobileNumber'},
  {key: 'alternateMobile'},
  {key: 'emailId'},
  {key: 'password'},
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
  // {uri, name, type} from the document picker — the offline XML, eAadhaar PDF, or
  // a photo of the card, or null. Kept as the picker's own object rather than a
  // bare uri string (every other attachment's shape) because the upload needs its
  // real name/type, which a bare uri can't carry, and which endpoint it routes to
  // depends on which of the three this turned out to be.
  aadhaarAttachment: null,
  // Only asked for if the broker's UIDAI XML download needed one — most don't set
  // a share code at all, so this is never required.
  aadhaarShareCode: '',
  // Only relevant for a password-protected eAadhaar PDF — UIDAI derives that
  // password from name + year of birth, so this is what verifyAadhaarEaadhaar
  // needs to open one. Unused for the XML or photo paths.
  aadhaarYob: '',
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
};

const RegisterScreen = ({navigation, route}) => {
  const {colors, radius, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  // Arrives from EmailOtpVerify once a code was confirmed for an email with no
  // account yet — already proven to belong to this person, so it's prefilled and
  // locked rather than asked for again.
  const verifiedEmail = route.params?.email ?? null;
  const [form, setForm] = useState(() =>
    verifiedEmail ? {...initialForm, emailId: verifiedEmail} : initialForm,
  );
  // Multiple photo/PDF attachments ride along on submit, so the upload can take a
  // real few seconds on a normal connection — with no loading state on the button,
  // that stretch looked exactly like "nothing happening" when the tap was already
  // registered and working.
  const isSubmitting = useAppSelector(state => state.auth.status === 'loading');
  const [errors, setErrors] = useState({});
  // Kicked off the moment an Aadhaar XML is attached, independent of the rest of
  // the form's validation/submit cycle — this is a live check of the document
  // itself, not something that should wait for "Submit" to run.
  const [aadhaarVerification, setAadhaarVerification] = useState({
    status: 'idle', // idle | verifying | verified | rejected | unavailable
    name: null,
    message: null,
  });
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
    if (!/^\+?[0-9]{7,15}$/.test(form.mobileNumber.trim())) {
      next.mobileNumber = 'Enter a valid mobile number';
    }
    if (
      form.alternateMobile.trim() &&
      !/^\+?[0-9]{7,15}$/.test(form.alternateMobile.trim())
    ) {
      next.alternateMobile = 'Enter a valid mobile number';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.emailId.trim())) {
      next.emailId = 'Enter a valid email';
    }
    if (!form.password || form.password.length < 8) {
      next.password = 'Password must be at least 8 characters';
    }
    if (!form.residenceAddress.trim()) {
      next.residenceAddress = 'Enter your address of communication';
    }
    // Optional — the backend accepts registration with no photo (`photo` is nullable),
    // and a profile with none just falls back to initials rather than blocking sign-up.
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
      next.aadhaarAttachment = 'Attach your Aadhaar (XML, PDF, or a photo of the card)';
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
    const remaining = Math.max(failed.length - 1, 0);

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

  /** Extension (lowercased, no dot) that decides which Surepass endpoint a picked file routes to. */
  const aadhaarDocumentKind = name => {
    const ext = (name ?? '').split('.').pop()?.toLowerCase();
    if (ext === 'xml') return 'xml';
    if (ext === 'pdf') return 'pdf';
    if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return 'image';
    return null;
  };

  /**
   * Opens the document picker for any of the three documents Surepass can verify
   * an Aadhaar from — the offline XML, the eAadhaar PDF, or a photo of the card —
   * then immediately verifies whatever was picked. Filtered to `allFiles` rather
   * than a specific MIME/UTI list: what a file manager reports varies enough
   * across devices that a strict type filter risked hiding the broker's own file
   * from the picker; the extension check below plus each endpoint's own server-side
   * `mimes:` validation are what actually gate this, not the picker's filter.
   */
  const pickAadhaarDocument = async () => {
    let picked;
    try {
      picked = await DocumentPicker.pickSingle({type: [DocumentPicker.types.allFiles]});
    } catch (error) {
      if (!isDocumentPickCancelled(error)) {
        Alert.alert('Could not open file picker', error?.message ?? 'Please try again.');
      }
      return;
    }

    const kind = aadhaarDocumentKind(picked.name);
    if (!kind) {
      Alert.alert(
        'Unsupported file',
        'Choose your Aadhaar offline XML, eAadhaar PDF, or a photo of the card (.xml, .pdf, .jpg, or .png).',
      );
      return;
    }

    const file = {uri: picked.uri, name: picked.name, type: picked.type || 'application/octet-stream'};
    update('aadhaarAttachment')(file);
    verifyAadhaarDocument(file, kind);
  };

  /**
   * Best-effort and non-blocking by design — every branch here just updates the
   * inline status shown under the attach box; nothing here stops the broker from
   * filling in the rest of the form or submitting regardless of what this call
   * answers (see KycController's own docblock for why: a bad file or an
   * unreachable Surepass must not gate registration itself).
   */
  const verifyAadhaarDocument = async (file, kind) => {
    setAadhaarVerification({status: 'verifying', name: null, message: null});

    try {
      const fullName = [form.suffix, form.fullNameAsRera].filter(Boolean).join(' ').trim();
      const {data} =
        kind === 'xml'
          ? await kycApi.verifyAadhaarXml(file, form.aadhaarShareCode.trim())
          : kind === 'pdf'
            ? await kycApi.verifyAadhaarEaadhaar(file, form.aadhaarYob.trim(), fullName)
            : await kycApi.verifyAadhaar(file);

      if (data.status === 'verified') {
        const verifiedName = data.data?.name ?? null;
        setAadhaarVerification({status: 'verified', name: verifiedName, message: null});

        // Only when the name field is still empty — a verified match should never
        // overwrite something the broker already typed themselves.
        if (verifiedName) {
          setForm(prev =>
            prev.fullNameAsRera.trim() ? prev : {...prev, fullNameAsRera: verifiedName},
          );
        }
        return;
      }

      setAadhaarVerification({status: data.status, name: null, message: data.message ?? null});
    } catch (error) {
      setAadhaarVerification({
        status: 'unavailable',
        name: null,
        message: extractError(error).message ?? 'Could not verify right now.',
      });
    }
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
    // What verifyAadhaarPhoto already found out about the attached photo, carried
    // into the same submit — see AuthController::register()'s note on why this is
    // trusted as reported rather than re-checked server-side.
    aadhaar_verified: aadhaarVerification.status === 'verified',
    aadhaar_verified_name: aadhaarVerification.status === 'verified' ? aadhaarVerification.name : null,
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
    // Already the {uri, name, type} the document picker returned, not a bare uri —
    // no filePart() needed (that helper's own hardcoded 'image/jpeg' type would be
    // wrong for this one anyway; this file is XML).
    aadhaar_file: form.aadhaarAttachment,
    rera_certificate_file: filePart(form.reraCertificateAttachment, 'rera-certificate.jpg'),
    gst_file: filePart(form.gstAttachment, 'gst.jpg'),
  });

  const handleSubmit = async () => {
    if (isSubmitting) {
      return;
    }
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
    // without the app having to be taught about it.
    const serverErrors = result.payload?.errors ?? {};
    const mapped = {};
    Object.entries(serverErrors).forEach(([apiField, messages]) => {
      const formField = SERVER_FIELD_TO_FORM[apiField];
      if (formField) {
        mapped[formField] = messages?.[0];
      }
    });

    const message = result.payload?.message;
    setErrors({
      ...mapped,
      submit: Object.keys(mapped).length === 0 ? message : undefined,
    });
    reportErrors(mapped, message);
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
            Register
          </AppText>
        </View>
        <AppText
          variant="caption"
          color={colors.textSecondary}
          style={{
            // Aligned under "Register", not the back chevron — matches the icon's
            // width plus the gap before "Register" in the row above.
            marginLeft: moderateScale(22) + spacing.sm,
            marginTop: spacing.xxs,
            marginBottom: spacing.xl,
          }}>
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
                ref={registerRef('fullNameAsRera')}
                label="Full name (as per RERA)"
                placeholder="e.g. Ravi Teja"
                value={form.fullNameAsRera}
                onChangeText={update('fullNameAsRera')}
              />
            </View>
          </View>

          <Input
            ref={registerRef('mobileNumber')}
            label="Mobile number *"
            placeholder="10-digit mobile"
            leftIcon="call-outline"
            keyboardType="phone-pad"
            value={form.mobileNumber}
            onChangeText={update('mobileNumber')}
            error={errors.mobileNumber}
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
            label={verifiedEmail ? 'Email ID (verified) *' : 'Email ID *'}
            placeholder="you@company.com"
            leftIcon={verifiedEmail ? 'checkmark-circle-outline' : 'mail-outline'}
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            editable={!verifiedEmail}
            value={form.emailId}
            onChangeText={update('emailId')}
            error={errors.emailId}
          />
          {/* Email + password is the login credential once an admin approves. */}
          <Input
            ref={registerRef('password')}
            label="Password *"
            placeholder="At least 8 characters"
            leftIcon="lock-closed-outline"
            isPassword
            value={form.password}
            onChangeText={update('password')}
            error={errors.password}
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

          {/* One picker, three accepted documents — see aadhaarDocumentKind(). The XML
              or eAadhaar PDF (both downloaded from UIDAI ahead of time) verify by a
              plain upload; a photo of the card still works too, verified by decoding
              its QR code, but is the least reliable of the three (a real, dense
              Aadhaar "Secure QR" needs more of the frame in sharp focus than a
              whole-card photo reliably delivers) — prefer XML or PDF when the broker
              has either. */}
          <AppText variant="caption" color={colors.textSecondary} style={styles.label}>
            Aadhaar (XML, PDF, or photo) *
          </AppText>
          <TouchableOpacity activeOpacity={0.85} onPress={pickAadhaarDocument} style={{marginBottom: spacing.xs}}>
            {form.aadhaarAttachment ? (
              <View
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  borderWidth: 1,
                  borderColor: colors.border,
                  borderRadius: radius.md,
                  paddingVertical: spacing.sm,
                  paddingHorizontal: spacing.sm,
                  backgroundColor: colors.background,
                }}>
                <Icon name="document-text-outline" size={moderateScale(20)} color={colors.primaryDark} />
                <AppText
                  variant="caption"
                  color={colors.textPrimary}
                  numberOfLines={1}
                  style={{flex: 1, marginLeft: spacing.xs}}>
                  {form.aadhaarAttachment.name}
                </AppText>
                <TouchableOpacity
                  onPress={() => {
                    update('aadhaarAttachment')(null);
                    setAadhaarVerification({status: 'idle', name: null, message: null});
                  }}
                  hitSlop={8}>
                  <Icon name="close-circle" size={moderateScale(18)} color={colors.textMuted} />
                </TouchableOpacity>
              </View>
            ) : (
              <View
                style={{
                  borderWidth: 1.5,
                  borderStyle: 'dashed',
                  borderColor: errors.aadhaarAttachment ? colors.danger : colors.primary,
                  borderRadius: radius.md,
                  paddingVertical: spacing.md,
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: colors.primarySoft,
                }}>
                <Icon name="document-attach-outline" size={moderateScale(20)} color={colors.primaryDark} />
                <AppText variant="caption" color={colors.primaryDark} style={{marginTop: moderateScale(4)}}>
                  Tap to attach — XML, PDF, or a photo of the card
                </AppText>
              </View>
            )}
          </TouchableOpacity>
          {errors.aadhaarAttachment && (
            <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(-2), marginBottom: spacing.xs}}>
              {errors.aadhaarAttachment}
            </AppText>
          )}

          <View style={{flexDirection: 'row'}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Input
                label="Share code (XML only, if any)"
                placeholder="4-digit code"
                keyboardType="number-pad"
                maxLength={4}
                value={form.aadhaarShareCode}
                onChangeText={update('aadhaarShareCode')}
              />
            </View>
            <View style={{flex: 1, marginLeft: spacing.xs}}>
              <Input
                label="Year of birth (PDF only, if any)"
                placeholder="e.g. 1990"
                keyboardType="number-pad"
                maxLength={4}
                value={form.aadhaarYob}
                onChangeText={update('aadhaarYob')}
              />
            </View>
          </View>

          {aadhaarVerification.status === 'verifying' && (
            <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: spacing.sm}}>
              <ActivityIndicator size="small" color={colors.primary} />
              <AppText variant="caption" color={colors.textSecondary} style={{marginLeft: spacing.xs}}>
                Verifying Aadhaar…
              </AppText>
            </View>
          )}
          {aadhaarVerification.status === 'verified' && (
            <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: spacing.sm}}>
              <Icon name="checkmark-circle" size={moderateScale(14)} color={colors.success} />
              <AppText variant="caption" color={colors.success} style={{marginLeft: spacing.xs}}>
                {aadhaarVerification.name ? `Verified — ${aadhaarVerification.name}` : 'Verified'}
              </AppText>
            </View>
          )}
          {/* qr_not_found is only reachable via the photo path — see verifyAadhaar's
              own status enum — the XML/PDF paths only ever answer 'rejected'. */}
          {(aadhaarVerification.status === 'rejected' || aadhaarVerification.status === 'qr_not_found') && (
            <View style={{flexDirection: 'row', alignItems: 'flex-start', marginBottom: spacing.sm}}>
              <Icon name="alert-circle-outline" size={moderateScale(14)} color={colors.warning} style={{marginTop: moderateScale(1)}} />
              <AppText variant="caption" color={colors.warning} style={{marginLeft: spacing.xs, flex: 1}}>
                {aadhaarVerification.message ?? 'Could not verify this file.'}
              </AppText>
            </View>
          )}
          {aadhaarVerification.status === 'unavailable' && (
            <AppText variant="caption" color={colors.textMuted} style={{marginBottom: spacing.sm}}>
              {aadhaarVerification.message ?? 'Could not verify right now.'}
            </AppText>
          )}

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

          {/* <DateField
            label="RERA certificate validity / expiry date *"
            placeholder="Tap to pick a date"
            value={form.reraCertificateExpiry}
            onChange={update('reraCertificateExpiry')}
            // A certificate that expired yesterday is not one to empanel against, so the
            // calendar simply cannot reach those days.
            minimumDate={TODAY}
            maximumDate={MAX_EXPIRY}
            error={errors.reraCertificateExpiry}
          /> */}

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
              onLinkPress={() => navigation.navigate('Terms')}
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
          label={isSubmitting ? 'Submitting…' : 'Submit for approval'}
          icon={isSubmitting ? undefined : 'arrow-forward'}
          iconPosition="right"
          onPress={handleSubmit}
          loading={isSubmitting}
          disabled={isSubmitting}
        />

        <View style={styles.footerRow}>
          <AppText variant="body" color={colors.textSecondary}>
            Already registered?{' '}
          </AppText>
          <TouchableOpacity
            // This form is channel-partner registration only — send them back into
            // that role's sign-in (email + OTP), not the developer password screen.
            onPress={() => navigation.replace('EmailOtpLogin')}
            hitSlop={8}>
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
