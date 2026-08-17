import React, {useEffect, useRef, useState} from 'react';
import {ActivityIndicator, Alert, Modal, StyleSheet, TouchableOpacity, View} from 'react-native';
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
  Dropdown,
  Input,
  ScreenContainer,
  SectionHeader,
  SignaturePad,
} from '../../components';
import {kycApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {startRegistration, saveRegistrationStep, logout} from '../../store/slices/authSlice';
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

const PAN_REGEX = /^[A-Z]{5}[0-9]{4}[A-Z]$/;

/** Picking "All" covers every other option, so the list has nothing left to offer. */
const TERMINAL_OPTIONS = ['All'];

/**
 * Every field the API can reject, mapped to the form field that shows it.
 *
 * `verification_token` has no field of its own — see AuthController::startRegistration,
 * which no longer trusts it at all; a step-1 rejection just becomes the general
 * submit error via the same fallback every other unmapped field already uses.
 */
const SERVER_FIELD_TO_FORM = {
  name: 'fullNameAsRera',
  email: 'emailId',
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
 * Fields in the order they appear on screen, each tagged with the wizard step it
 * belongs to. `reportErrors` filters this by whichever keys the current step's
 * validation actually failed, so "jump to the first thing wrong" still works without
 * this list needing to change shape per step.
 *
 * `focusVia` is for the controls that cannot take keyboard focus — an attachment box
 * or a checkbox — and names the nearest text input above them. `mobileNumber` is not
 * in this list when it's locked (a verified mobile-OTP session), but the field itself
 * still exists — see the component for when it is/isn't editable.
 */
const FIELD_ORDER = [
  {key: 'fullNameAsRera', step: 1},
  {key: 'mobileNumber', step: 1},
  {key: 'alternateMobile', step: 1},
  {key: 'emailId', step: 1},
  {key: 'residenceAddress', step: 1},
  {key: 'photoAttachment', step: 1, focusVia: 'residenceAddress'},
  {key: 'state', step: 2},
  {key: 'city', step: 2},
  {key: 'segments', step: 2, focusVia: 'city'},
  {key: 'zones', step: 2, focusVia: 'city'},
  {key: 'projectContributions', step: 2},
  {key: 'companyName', step: 3},
  {key: 'officeAddress', step: 3},
  {key: 'companyWebsite', step: 3},
  {key: 'instagram', step: 3},
  {key: 'facebook', step: 3},
  {key: 'youtube', step: 3},
  {key: 'twitter', step: 3},
  {key: 'linkedin', step: 3},
  {key: 'yearsOfExperience', step: 3},
  {key: 'teamSize', step: 3},
  {key: 'panCard', step: 3},
  {key: 'panCardAttachment', step: 3, focusVia: 'panCard'},
  {key: 'aadhaarCard', step: 3},
  {key: 'aadhaarAttachment', step: 3, focusVia: 'aadhaarCard'},
  {key: 'reraNumber', step: 3},
  {key: 'reraCertificateAttachment', step: 3, focusVia: 'reraNumber'},
  {key: 'gstNumber', step: 3},
  {key: 'confirmAccuracy', step: 3},
  {key: 'signature', step: 3},
];

/**
 * What the progress bar checks to tell "reached this step but nothing saved on
 * it yet" apart from "actually Save-Drafted something here" — reaching step N
 * (registrationStep advancing past N-1) says nothing about step N's own data;
 * only a field belonging to it landing in `draftProfile` does. Booleans
 * (is_company, operates_multiple_states, confirm_accuracy) are excluded on
 * purpose: `false` is indistinguishable from untouched, so they are not a
 * reliable "something was saved here" signal the way a filled string is.
 * Step 1 needs no entry — see `stepStatus` below, it has its own rule.
 */
const STEP_DRAFT_FIELDS = {
  2: ['state', 'city', 'project_contributions'],
  3: [
    'company_name', 'office_address', 'company_website', 'instagram', 'facebook',
    'youtube', 'twitter', 'linkedin', 'years_of_experience', 'team_size',
    'pan_card', 'pan_card_path', 'aadhaar_card', 'aadhaar_path',
    'rera_number', 'rera_certificate_path', 'gst_number', 'gst_path', 'signature_path',
  ],
};
const STEP_DRAFT_ARRAY_FIELDS = {2: ['segments', 'zones']};

/** A previously-saved attachment comes back as a full URL — nothing local to re-upload. */
const isRemoteUri = uri => typeof uri === 'string' && /^https?:\/\//.test(uri);

/**
 * Reverses the suffix+full-name join `toStepPayload(1)` does when submitting `name` —
 * needed to seed the form when resuming a draft session, where all the app gets back
 * is the single combined string it originally sent.
 */
const splitName = name => {
  if (!name) {
    return {suffix: '', fullNameAsRera: ''};
  }
  const [first, ...rest] = name.trim().split(' ');
  if (SUFFIX_OPTIONS.includes(first) && rest.length) {
    return {suffix: first, fullNameAsRera: rest.join(' ')};
  }
  return {suffix: '', fullNameAsRera: name.trim()};
};

/**
 * `draft` is `state.auth.draftProfile` (UserResource::draftProfile() — see the backend
 * resource) when resuming an in-progress registration; undefined for a brand new one.
 * `identity` carries name/email/mobile, which live on the User row, not the profile.
 */
const buildInitialForm = ({mobile, email, identity, draft}) => {
  const {suffix, fullNameAsRera} = splitName(identity?.name);

  return {
    suffix,
    fullNameAsRera,
    mobileNumber: identity?.mobile ?? mobile ?? '',
    alternateMobile: draft?.alternate_mobile ?? '',
    emailId: identity?.email ?? email ?? '',
    residenceAddress: draft?.residence_address ?? '',
    photoAttachment: draft?.photo_path ?? '',
    isCompany: draft?.is_company ?? false,
    companyName: draft?.company_name ?? '',
    officeAddress: draft?.office_address ?? '',
    sameAsResidenceAddress: false,
    companyWebsite: draft?.company_website ?? '',
    instagram: draft?.instagram ?? '',
    facebook: draft?.facebook ?? '',
    youtube: draft?.youtube ?? '',
    twitter: draft?.twitter ?? '',
    linkedin: draft?.linkedin ?? '',
    yearsOfExperience: draft?.years_of_experience ? String(draft.years_of_experience) : '',
    teamSize: draft?.team_size ? String(draft.team_size) : '',
    panCard: draft?.pan_card ?? '',
    panCardAttachment: draft?.pan_card_path ?? '',
    aadhaarCard: draft?.aadhaar_card ?? '',
    // The picker's own {uri, name, type} shape is what the field expects; a resumed
    // remote path only has a URL, so this is a display-only stand-in — isRemoteUri()
    // is what stops it from ever being re-uploaded as if it were a fresh pick.
    aadhaarAttachment: draft?.aadhaar_path
      ? {uri: draft.aadhaar_path, name: draft.aadhaar_path.split('/').pop(), type: 'application/octet-stream'}
      : null,
    aadhaarShareCode: '',
    aadhaarYob: '',
    reraNumber: draft?.rera_number ?? '',
    reraCertificateAttachment: draft?.rera_certificate_path ?? '',
    gstNumber: draft?.gst_number ?? '',
    gstAttachment: draft?.gst_path ?? '',
    state: draft?.state ?? '',
    city: draft?.city ?? '',
    segments: draft?.segments ?? [],
    zones: draft?.zones ?? [],
    projectContributions: draft?.project_contributions ?? '',
    operatesMultipleStates: draft?.operates_multiple_states ?? false,
    confirmAccuracy: false,
    hasSignature: !!draft?.signature_path,
  };
};

/**
 * The channel-partner empanelment form — reached after OtpVerifyScreen/
 * EmailOtpVerifyScreen confirm a mobile/email with no *finished* account for it (or a
 * `draft` one already in progress). Split into 3 steps — Personal -> Business ->
 * Professional, deliberately ending on the heaviest step (company details, every
 * KYC attachment, plus the final agreement + signature) so the lighter,
 * text-only fields come first and everything requiring an upload or a decision
 * comes right before the moment of actually submitting. Each step's Next saves to
 * the database immediately — see `startRegistration` (step 1 only, creates the
 * account) and `saveRegistrationStep` (every step after, including Save Draft on any
 * step, including step 1's own fields being edited again after Back). A `draft`
 * session persists and resumes on its own — see RootNavigator — so closing the app
 * mid-form and reopening it lands back here, at whichever step was last saved, with
 * that data still filled in.
 */
const CompleteProfileScreen = ({navigation, route}) => {
  const {colors, radius, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const mobileParam = route.params?.mobile;
  const emailParam = route.params?.email;

  const registrationStatus = useAppSelector(state => state.auth.registrationStatus);
  const registrationStep = useAppSelector(state => state.auth.registrationStep);
  const draftProfile = useAppSelector(state => state.auth.draftProfile);
  const user = useAppSelector(state => state.auth.user);
  const isSubmitting = useAppSelector(state => state.auth.status === 'loading');
  const isResuming = registrationStatus === 'draft';

  // mobileParam only exists on the (currently unlinked) mobile-OTP path, immediately
  // after verification — resuming a draft session has no route params at all, so the
  // already-created account's own mobile (in `user`, from the token) is what seeds it
  // then. Once either is true, the identity fields are locked — see the Input below.
  const isIdentityLocked = !!mobileParam || isResuming;

  const [currentStep, setCurrentStep] = useState(() => (isResuming ? registrationStep || 1 : 1));
  // RootNavigator can mount this screen from the *persisted* registrationStep
  // (loaded from storage) before the boot-time `fetchMe()` re-validation — fired
  // right alongside it, not awaited — actually resolves with the server's own
  // value. If that arrives a moment later with something further along, this
  // corrects the step once. It self-disables the instant the broker does
  // anything themselves (see the ref set in the handlers below), so it can
  // never fight a deliberate Back navigation later in the same session.
  const hasNavigatedRef = useRef(false);
  const [form, setForm] = useState(() =>
    buildInitialForm({mobile: mobileParam, email: emailParam, identity: user, draft: draftProfile}),
  );
  const [errors, setErrors] = useState({});
  // Kicked off the moment an Aadhaar document is attached, independent of the rest
  // of the form's validation/submit cycle — this is a live check of the document
  // itself, not something that should wait for "Next" to run.
  const [aadhaarVerification, setAadhaarVerification] = useState({
    status: 'idle', // idle | verifying | verified | rejected | unavailable | qr_not_found
    name: null,
    message: null,
  });
  // Same idea as aadhaarVerification, kicked off on blur instead of on attach
  // (there's no file to pick — just a number). Confirms the PAN is real and
  // shows the name on file for it; does not auto-fill any other field — PAN
  // lives on step 3, after step 1 (name) and step 2 (address/city/state) have
  // already been saved, so silently rewriting those here would look like it
  // worked but never reach the backend unless the broker revisits those steps.
  const [panVerification, setPanVerification] = useState({
    status: 'idle', // idle | verifying | verified | rejected | unavailable
    name: null,
    message: null,
  });
  const [isScrollEnabled, setIsScrollEnabled] = useState(true);
  // Shown instead of a snackbar + auto-navigate once step 3's real submit finalizes
  // the registration — a snackbar is easy to miss, and by the time it would fire
  // `navigation.replace('PendingApproval')` this screen's own session is already
  // gone (see saveRegistrationStep.fulfilled: token/isLoggedIn are cleared the
  // moment status flips to `pending`), so an explicit "Back to Login" tap is a more
  // reliable way to end up somewhere valid than a screen transition racing that.
  const [submittedMessage, setSubmittedMessage] = useState(null);

  // One ref per text field, so a failed submit can put the cursor in the offending one.
  // Focusing is also what scrolls it into view — KeyboardAwareScrollView follows focus.
  const inputRefs = useRef({});
  const registerRef = key => node => {
    inputRefs.current[key] = node;
  };

  useEffect(() => {
    if (isResuming && !hasNavigatedRef.current && registrationStep && registrationStep !== currentStep) {
      setCurrentStep(registrationStep);
    }
  }, [isResuming, registrationStep, currentStep]);

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

  /** One branch per step — only what that step's own fields need. */
  const validateStep = step => {
    const next = {};

    if (step === 1) {
      if (!isIdentityLocked && !form.mobileNumber.trim()) {
        next.mobileNumber = 'Enter your mobile number';
      } else if (form.mobileNumber.trim() && !/^\+?[0-9]{7,15}$/.test(form.mobileNumber.trim())) {
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
      if (!form.residenceAddress.trim()) {
        next.residenceAddress = 'Enter your address of communication';
      }
    } else if (step === 2) {
      // Business info — nothing here is required, same as before this became its
      // own step: state/city/segments/zones/project contributions are all optional.
    } else {
      // step 3 — Professional info, plus the final agreement + signature.
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
      if (!form.reraCertificateAttachment) {
        next.reraCertificateAttachment = 'Attach the RERA certificate';
      }
      if (!form.confirmAccuracy) {
        next.confirmAccuracy = 'Please confirm to continue';
      }
      if (!form.hasSignature) {
        next.signature = 'Please sign to continue';
      }
    }

    setErrors(next);
    return next;
  };

  /**
   * Takes the user to the first thing that is wrong and says what it is.
   *
   * This is the whole reason the form "did not submit": validation was refusing, but the
   * only sign of it was a red line under a field that could be off the bottom of the
   * step. Pressing Next looked like nothing happened at all.
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
   * `null` for a resumed remote path too — see isRemoteUri() — since there is
   * nothing local to send again for something already saved.
   *
   * `type` is declared as JPEG because the picker only ever returns camera/gallery
   * images here; the server sniffs the real mime anyway and accepts PDF too, so a
   * mislabelled part is validated on its contents rather than this hint.
   */
  const filePart = (uri, fallbackName) =>
    uri && !isRemoteUri(uri)
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

  /** Best-effort and non-blocking, same contract as verifyAadhaarDocument. */
  const verifyPan = async panNumber => {
    setPanVerification({status: 'verifying', name: null, message: null});

    try {
      const {data} = await kycApi.verifyPan(panNumber);
      setPanVerification({
        status: data.status,
        name: data.status === 'verified' ? data.data?.full_name ?? null : null,
        message: data.status === 'verified' ? null : data.message ?? null,
      });
    } catch (error) {
      setPanVerification({
        status: 'unavailable',
        name: null,
        message: extractError(error).message ?? 'Could not verify right now.',
      });
    }
  };

  /** Fires once the PAN field loses focus with a complete, correctly-formatted PAN — not on every keystroke. */
  const handlePanBlur = () => {
    const pan = form.panCard.trim().toUpperCase();
    if (PAN_REGEX.test(pan)) {
      verifyPan(pan);
    }
  };

  /** Maps the current step's form fields onto that step's slice of the API contract. */
  const toStepPayload = step => {
    if (step === 1) {
      return {
        name: [form.suffix, form.fullNameAsRera].filter(Boolean).join(' ').trim(),
        email: form.emailId.trim(),
        mobile: form.mobileNumber.trim(),
        alternate_mobile: form.alternateMobile.trim() || null,
        residence_address: form.residenceAddress.trim() || null,
        // The picker hands back a local file:// URI. The transport layer turns this
        // into a multipart part; sending the URI as a plain string would store a
        // path that only ever resolved on the device that typed it.
        photo: filePart(form.photoAttachment, 'photo.jpg'),
      };
    }

    if (step === 2) {
      return {
        state: form.state.trim() || null,
        city: form.city.trim() || null,
        segments: form.segments,
        zones: form.zones,
        operates_multiple_states: form.operatesMultipleStates,
        project_contributions: form.projectContributions.trim() || null,
      };
    }

    // step 3 — Professional info, plus the final agreement.
    return {
      is_company: form.isCompany,
      company_name: form.companyName.trim() || null,
      office_address: form.officeAddress.trim() || null,
      company_website: form.companyWebsite.trim() || null,
      instagram: form.instagram.trim() || null,
      facebook: form.facebook.trim() || null,
      youtube: form.youtube.trim() || null,
      twitter: form.twitter.trim() || null,
      linkedin: form.linkedin.trim() || null,
      years_of_experience: form.yearsOfExperience ? Number(form.yearsOfExperience) : null,
      team_size: form.teamSize ? Number(form.teamSize) : null,
      pan_card: form.panCard.trim() || null,
      // What verifyPan already found out about the typed PAN number, carried into
      // the same save — same trust boundary as aadhaar_verified below.
      pan_verified: panVerification.status === 'verified',
      pan_verified_name: panVerification.status === 'verified' ? panVerification.name : null,
      aadhaar_card: form.aadhaarCard.trim() || null,
      // What verifyAadhaarDocument already found out about the attached document,
      // carried into the same save — see AuthController's note on why this is
      // trusted as reported rather than re-checked server-side.
      aadhaar_verified: aadhaarVerification.status === 'verified',
      aadhaar_verified_name: aadhaarVerification.status === 'verified' ? aadhaarVerification.name : null,
      rera_number: form.reraNumber.trim() || null,
      gst_number: form.gstNumber.trim() || null,
      pan_card_file: filePart(form.panCardAttachment, 'pan-card.jpg'),
      // Already the {uri, name, type} the document picker returned, not a bare
      // uri — no filePart() needed (and it might be XML or PDF, not JPEG).
      aadhaar_file:
        form.aadhaarAttachment && !isRemoteUri(form.aadhaarAttachment.uri)
          ? form.aadhaarAttachment
          : null,
      rera_certificate_file: filePart(form.reraCertificateAttachment, 'rera-certificate.jpg'),
      gst_file: filePart(form.gstAttachment, 'gst.jpg'),
      confirm_accuracy: form.confirmAccuracy,
    };
  };

  /**
   * Dispatches whichever thunk this step actually needs: `startRegistration` only for
   * step 1's very first save (nothing exists server-side yet to PATCH), everything
   * else — later steps, and step 1 being edited again after Back — goes through the
   * now-authenticated `saveRegistrationStep`. Returns the thunk alongside the result
   * so the caller can match `.fulfilled`/`.rejected` against the right one.
   */
  const persistStep = async (step, saveDraft) => {
    const isFirstSave = step === 1 && !isResuming;
    const thunk = isFirstSave ? startRegistration : saveRegistrationStep;
    const payload = isFirstSave
      ? {save_draft: saveDraft, ...toStepPayload(1)}
      : {step, save_draft: saveDraft, ...toStepPayload(step)};

    const result = await dispatch(thunk(payload));
    return {result, thunk};
  };

  /** Maps a 422's field errors onto the form and reports them, same shape either action needs. */
  const applyServerErrors = (result, fallbackMessage) => {
    const serverErrors = result.payload?.errors ?? {};
    const mapped = {};

    Object.entries(serverErrors).forEach(([apiField, messages]) => {
      const formField = SERVER_FIELD_TO_FORM[apiField];
      if (formField) {
        mapped[formField] = messages?.[0];
      }
    });

    const message = result.payload?.message ?? fallbackMessage;
    setErrors({...mapped, submit: Object.keys(mapped).length === 0 ? message : undefined});
    reportErrors(mapped, message);
  };

  const handleNext = async () => {
    hasNavigatedRef.current = true;
    const stepErrors = validateStep(currentStep);
    if (Object.keys(stepErrors).length > 0) {
      reportErrors(stepErrors);
      return;
    }

    const {result, thunk} = await persistStep(currentStep, false);

    if (thunk.fulfilled.match(result)) {
      // Only step 3's real Next flips the account to `pending` server-side — trust
      // that, not the local step counter, so a save that happened to be step 3 but
      // was rejected (or was a Save Draft) never navigates away early.
      if (result.payload?.data?.status === 'pending') {
        setSubmittedMessage(
          result.payload?.message ??
            "Your registration is pending admin approval. We'll notify you the moment your account is verified.",
        );
        return;
      }

      setCurrentStep(step => Math.min(step + 1, 3));
      return;
    }

    applyServerErrors(result, 'Could not save this step.');
  };

  const handleBack = () => {
    hasNavigatedRef.current = true;
    if (currentStep > 1) {
      setCurrentStep(step => step - 1);
      return;
    }

    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    // A resumed draft session mounts this screen as the stack's own root (see
    // RootNavigator's `initialRouteName="CompleteProfile"` branch) — there is no
    // OTP/Welcome screen underneath it to return to, and calling goBack() anyway
    // is exactly what used to throw "The action 'GOBACK' was not handled by any
    // navigator." The saved draft is untouched server-side either way — signing
    // out here just ends the local session; re-verifying OTP with the same
    // mobile/email resumes right back at this step (see the 'draft' status fork
    // in verifyOtp/verifyEmailOtp).
    dispatch(logout());
    navigation.navigate('Welcome');
  };

  /**
   * Persists whatever is filled in on the current step, incomplete or not — no
   * validation gate. Stays on the same step: this is "keep what I have so far", not
   * "move on".
   */
  const handleSaveDraft = async () => {
    hasNavigatedRef.current = true;
    const {result, thunk} = await persistStep(currentStep, true);

    if (thunk.fulfilled.match(result)) {
      dispatch(showSnackbar({message: 'Draft saved.', tone: 'success'}));
      return;
    }

    applyServerErrors(result, 'Could not save draft.');
  };

  /**
   * The progress bar's 3-colour states — driven by what the server actually holds
   * (`registrationStep`/`draftProfile`), not by `currentStep` (which just tracks
   * where the wizard is scrolled to right now, and used to be what this read,
   * which is why a broker sitting on step 3 saw all three segments filled in even
   * though only steps 1-2 were ever actually completed).
   *
   *   completed — every step before `registrationStep`: its own real "Next" is
   *               what advanced past it (see AuthController::saveRegistrationStep).
   *   draft     — `registrationStep` itself, when something belonging to it has
   *               actually been saved. Step 1 is a special case: `registrationStep`
   *               only ever stays at 1 because `startRegistration` was called with
   *               `save_draft: true` (a real "Next" always leaves it at 2 or more —
   *               see the backend), so reaching a draft session at step 1 already
   *               means step 1 was draft-saved, with nothing further to check.
   *   empty     — anything after `registrationStep`, or step 1 of a session that
   *               was never even draft-saved once (`!isResuming`).
   */
  const stepHasDraftData = step => {
    if (!draftProfile) {
      return false;
    }
    const fields = STEP_DRAFT_FIELDS[step] ?? [];
    if (fields.some(field => !!draftProfile[field])) {
      return true;
    }
    const arrayFields = STEP_DRAFT_ARRAY_FIELDS[step] ?? [];
    return arrayFields.some(field => (draftProfile[field]?.length ?? 0) > 0);
  };

  const stepColor = step => {
    if (isResuming && step < registrationStep) {
      return colors.primary;
    }
    if (isResuming && step === registrationStep) {
      return (step === 1 || stepHasDraftData(step)) ? colors.warning : colors.border;
    }
    return colors.border;
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
          <TouchableOpacity onPress={handleBack} hitSlop={10}>
            <Icon
              name="chevron-back"
              size={moderateScale(22)}
              color={colors.textPrimary}
            />
          </TouchableOpacity>
          <AppText
            variant="h1"
            numberOfLines={1}
            ellipsizeMode="tail"
            style={{marginLeft: spacing.sm, flex: 1}}>
            Complete Profile
          </AppText>
          {/* Text-only on purpose — this is a lightweight "keep what I have" escape
              hatch that should sit quietly next to the title, not compete with the
              Next/Submit button for visual weight. */}
          <TouchableOpacity onPress={handleSaveDraft} disabled={isSubmitting} hitSlop={8}>
            <AppText variant="bodyMedium" color={isSubmitting ? colors.textMuted : colors.primary}>
              Save Draft
            </AppText>
          </TouchableOpacity>
        </View>

        {/* Small step progress — colour per step's actual save state (see
            `stepColor` above), not which one is on screen right now. A broker
            scrolled to step 3 who never saved anything there sees step 3 empty,
            not "completed" just because it happens to be in view. */}
        <View
          style={{
            flexDirection: 'row',
            marginTop: spacing.md,
          }}>
          {[1, 2, 3].map(step => (
            <View
              key={step}
              style={{
                flex: 1,
                height: moderateScale(4),
                borderRadius: moderateScale(2),
                marginRight: step < 3 ? spacing.xs : 0,
                backgroundColor: stepColor(step),
              }}
            />
          ))}
        </View>
        <AppText
          variant="caption"
          color={colors.textSecondary}
          style={{
            marginTop: spacing.xs,
            marginBottom: spacing.xl,
          }}>
          Step {currentStep} of 3 — a few more details and you're ready for admin review.
        </AppText>

        {currentStep === 1 && (
          <>
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

              {/* Locked once this is the number/session OTP verification already
                  confirmed — editable only for a brand-new email-path registration,
                  where nothing has verified a mobile number yet at all. */}
              <Input
                ref={registerRef('mobileNumber')}
                label="Mobile number *"
                placeholder="10-digit mobile"
                leftIcon="call-outline"
                keyboardType="phone-pad"
                value={form.mobileNumber}
                onChangeText={update('mobileNumber')}
                editable={!isIdentityLocked}
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
                label="Email ID *"
                placeholder="you@company.com"
                leftIcon="mail-outline"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                value={form.emailId}
                onChangeText={update('emailId')}
                editable={!isIdentityLocked}
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
          </>
        )}

        {currentStep === 2 && (
          <>
            <SectionHeader step={2} title="Business info" />
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
            </View>
          </>
        )}

        {currentStep === 3 && (
          <>
            <SectionHeader step={3} title="Professional info" />
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
                onBlur={handlePanBlur}
                error={errors.panCard}
              />
              {panVerification.status === 'verifying' && (
                <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: spacing.sm}}>
                  <ActivityIndicator size="small" color={colors.primary} />
                  <AppText variant="caption" color={colors.textSecondary} style={{marginLeft: spacing.xs}}>
                    Verifying PAN…
                  </AppText>
                </View>
              )}
              {panVerification.status === 'verified' && (
                <View style={{flexDirection: 'row', alignItems: 'flex-start', marginBottom: spacing.sm}}>
                  <Icon name="checkmark-circle" size={moderateScale(14)} color={colors.success} style={{marginTop: moderateScale(1)}} />
                  <AppText variant="caption" color={colors.success} style={{marginLeft: spacing.xs, flex: 1}}>
                    {panVerification.name ? `Verified — ${panVerification.name}` : 'Verified'}
                  </AppText>
                </View>
              )}
              {panVerification.status === 'rejected' && (
                <View style={{flexDirection: 'row', alignItems: 'flex-start', marginBottom: spacing.sm}}>
                  <Icon name="alert-circle-outline" size={moderateScale(14)} color={colors.warning} style={{marginTop: moderateScale(1)}} />
                  <AppText variant="caption" color={colors.warning} style={{marginLeft: spacing.xs, flex: 1}}>
                    {panVerification.message ?? 'Could not verify this PAN.'}
                  </AppText>
                </View>
              )}
              {panVerification.status === 'unavailable' && (
                <AppText variant="caption" color={colors.textMuted} style={{marginBottom: spacing.sm}}>
                  {panVerification.message ?? 'Could not verify right now.'}
                </AppText>
              )}
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
                <View style={{flexDirection: 'row', alignItems: 'flex-start', marginBottom: spacing.sm}}>
                  <Icon name="checkmark-circle" size={moderateScale(14)} color={colors.success} style={{marginTop: moderateScale(1)}} />
                  <AppText variant="caption" color={colors.success} style={{marginLeft: spacing.xs, flex: 1}}>
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

              <View style={{marginBottom: spacing.lg, marginTop: spacing.md}}>
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
          </>
        )}

        {errors.submit && (
          <AppText
            variant="caption"
            color={colors.danger}
            style={{textAlign: 'center', marginBottom: spacing.sm}}>
            {errors.submit}
          </AppText>
        )}

        <View style={{flexDirection: 'row', marginBottom: spacing.sm}}>
          {currentStep > 1 && (
            <Button
              label="Back"
              variant="outline"
              fullWidth={false}
              disabled={isSubmitting}
              onPress={handleBack}
              style={{flex: 1, marginRight: spacing.xs}}
            />
          )}
          <Button
            label={currentStep === 3 ? 'Submit for approval' : 'Next'}
            icon="arrow-forward"
            iconPosition="right"
            fullWidth={false}
            loading={isSubmitting}
            onPress={handleNext}
            style={{flex: 1, marginLeft: currentStep > 1 ? spacing.xs : 0}}
          />
        </View>

        {currentStep === 1 && (
          <View style={styles.footerRow}>
            <AppText variant="body" color={colors.textSecondary}>
              Wrong number?{' '}
            </AppText>
            <TouchableOpacity onPress={handleBack} hitSlop={8}>
              <AppText variant="bodyMedium" color={colors.primary}>
                Start over
              </AppText>
            </TouchableOpacity>
          </View>
        )}
      </KeyboardAwareScrollView>

      {/* Terminal state, not a dismissible confirmation — the session behind this
          screen is already gone the instant this shows (see the note on
          `submittedMessage`), so there is nothing to "cancel" back to. Both the
          button and the backdrop/hardware-back close the same way, out to Login. */}
      <Modal
        visible={!!submittedMessage}
        transparent
        animationType="fade"
        statusBarTranslucent
        navigationBarTranslucent
        onRequestClose={() => navigation.replace('EmailOtpLogin')}>
        <View style={[styles.modalBackdrop, {backgroundColor: colors.overlayStrong}]}>
          <View
            style={[
              styles.modalCard,
              {backgroundColor: colors.card, borderRadius: radius.lg, padding: spacing.lg},
            ]}>
            <View style={{alignItems: 'center'}}>
              <Icon name="checkmark-circle" size={moderateScale(40)} color={colors.success} />
              <AppText variant="h3" align="center" style={{marginTop: spacing.sm}}>
                Registration submitted
              </AppText>
              <AppText
                variant="body"
                color={colors.textSecondary}
                align="center"
                style={{marginTop: spacing.xs}}>
                {submittedMessage}
              </AppText>
            </View>
            <View style={{marginTop: spacing.lg}}>
              <Button
                label="Back to Login"
                icon="arrow-back-outline"
                onPress={() => navigation.replace('EmailOtpLogin')}
              />
            </View>
          </View>
        </View>
      </Modal>
    </ScreenContainer>
  );
};

const styles = StyleSheet.create({
  label: {
    marginBottom: moderateScale(5),
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: moderateScale(20),
  },
  modalBackdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: moderateScale(24),
  },
  modalCard: {
    width: '100%',
    maxWidth: moderateScale(360),
  },
});

export default CompleteProfileScreen;
