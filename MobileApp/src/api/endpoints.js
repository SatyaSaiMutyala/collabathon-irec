import client from './client';
import {UPLOAD_TIMEOUT_MS} from './config';

/**
 * Thin transport layer — one function per endpoint, no state.
 * Every list call forwards pagination/search/filter params to the server;
 * nothing is filtered client-side.
 */

/**
 * Flattens a payload into FormData for the one endpoint that carries a file.
 *
 * Multipart has no types — everything arrives as a string — so booleans go as 1/0 for
 * Laravel's `boolean` rule, arrays go as `key[]` repeats so they arrive as arrays, and
 * nulls are dropped rather than sent as the literal "null" that a `nullable` rule would
 * happily store. The file part is the {uri, name, type} object React Native's fetch
 * understands.
 */
/** A picker result: the {uri, name, type} shape React Native's fetch sends as a file. */
const isFilePart = value =>
  value !== null && typeof value === 'object' && typeof value.uri === 'string';

function toFormData(payload) {
  const form = new FormData();

  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') {
      return;
    }

    // Detected by shape, not by name. This used to test `key === 'photo'`, so every
    // other attachment fell through to String(value) and was posted as the literal
    // "[object Object]" — which is why the KYC scans never reached the server.
    if (isFilePart(value)) {
      form.append(key, value);
      return;
    }

    if (Array.isArray(value)) {
      value.forEach(entry => form.append(`${key}[]`, String(entry)));
      return;
    }

    form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
  });

  return form;
}

// ---------------------------------------------------------------- auth
// ---------------------------------------------------------------- public config
export const configApi = {
  /** Admin-controlled, read before sign-in — see ConfigController. */
  fetch: () => client.get('/config'),
};

export const authApi = {
  /**
   * Step 1 (Personal info) of the 3-step registration wizard — creates the draft
   * account and issues the Sanctum token every later step authenticates with. Same
   * JSON-vs-multipart fork as `saveRegistrationStep` below: a plain JSON body cannot
   * carry the profile photo, and forcing multipart when nothing was picked would turn
   * every boolean into a string the server then has to un-stringify for no reason.
   */
  startRegistration: payload =>
    Object.values(payload).some(isFilePart)
      ? client.post('/auth/register/start', toFormData(payload), {
          headers: {'Content-Type': 'multipart/form-data'},
          timeout: UPLOAD_TIMEOUT_MS,
        })
      : client.post('/auth/register/start', payload),
  /**
   * Steps 2-3, and Save Draft on any step — `payload.save_draft` decides whether the
   * server enforces that step's required fields (`false`, "Next"/final submit) or
   * accepts whatever's present as-is (`true`). Same file-detection fork as above.
   */
  saveRegistrationStep: payload =>
    Object.values(payload).some(isFilePart)
      ? client.patch('/auth/register/step', toFormData(payload), {
          headers: {'Content-Type': 'multipart/form-data'},
          timeout: UPLOAD_TIMEOUT_MS,
        })
      : client.patch('/auth/register/step', payload),
  /** Shared sign-in for both mobile roles — see LoginScreen. */
  login: payload => client.post('/auth/login', payload),
  me: () => client.get('/auth/me'),
  logout: () => client.post('/auth/logout'),
  /** Self-service account deletion — soft delete server-side, see AuthController. */
  deleteAccount: () => client.delete('/auth/account'),

  // ---------------------------------------------------------------- channel-partner OTP
  /** Issues (or re-issues) a 6-digit code for a mobile number. */
  sendOtp: mobile => client.post('/auth/otp/send', {mobile}),
  /**
   * The fork point: `status` in the response is 'login' (token + user), 'draft'
   * (a registration already in progress — token + the profile so far, so the wizard
   * can resume where it left off), 'register' (no account yet — carries a
   * verification_token, currently unused since this path isn't wired into any
   * client build), or 'pending' / 'rejected'.
   */
  verifyOtp: ({mobile, code}) =>
    client.post('/auth/otp/verify', {mobile, code, device_name: 'mobile'}),

  // ---------------------------------------------------------------- channel-partner email OTP
  /** Issues (or re-issues) a 4-digit code for an email address. */
  sendEmailOtp: email => client.post('/auth/email-otp/send', {email}),
  /**
   * Same fork shape as `verifyOtp`: `status` is 'login' (token + user), 'draft' (an
   * in-progress registration resumes with a fresh token + the profile so far),
   * 'register' (no account yet — the app carries the verified email into step 1 of
   * CompleteProfileScreen), or 'pending' / 'rejected'.
   */
  verifyEmailOtp: ({email, code}) =>
    client.post('/auth/email-otp/verify', {email, code, device_name: 'mobile'}),
};

// ---------------------------------------------------------------- KYC verification
export const kycApi = {
  /**
   * A photo of the physical Aadhaar card in, its QR code checked against Surepass out.
   * `photo` is the {uri, name, type} picker result — see filePart() on the calling
   * screen. Responds 200 either way; `status` in the body is what actually says
   * whether it verified (see KycController::verifyAadhaar).
   */
  verifyAadhaar: photo =>
    client.post('/kyc/aadhaar/verify', toFormData({aadhaar_photo: photo}), {
      headers: {'Content-Type': 'multipart/form-data'},
    }),

  /**
   * The offline XML a broker downloads themselves from the UIDAI website in,
   * verified data out — no photo/QR-decode fragility, since there's no decode step
   * at all. `xml` is the {uri, name, type} document-picker result; `shareCode` is
   * the 4-digit code UIDAI has the downloader set, sent along only if they typed one.
   */
  verifyAadhaarXml: (xml, shareCode) =>
    client.post(
      '/kyc/aadhaar/verify-xml',
      toFormData({aadhaar_xml: xml, share_code: shareCode || null}),
      {headers: {'Content-Type': 'multipart/form-data'}},
    ),

  /**
   * The eAadhaar PDF a broker downloads themselves from the UIDAI website in,
   * verified data out. `yob`/`fullName` are what a password-protected copy needs
   * to open — sent along only when the broker has typed them.
   */
  verifyAadhaarEaadhaar: (pdf, yob, fullName) =>
    client.post(
      '/kyc/aadhaar/verify-eaadhaar',
      toFormData({aadhaar_eaadhaar: pdf, yob: yob || null, full_name: fullName || null}),
      {headers: {'Content-Type': 'multipart/form-data'}},
    ),

  /**
   * A PAN number in, Surepass's PAN Comprehensive data out — name, DOB, gender,
   * address, masked Aadhaar, Aadhaar-linkage status. Used to auto-fill the rest
   * of the form once a broker types a valid PAN.
   */
  verifyPan: panNumber => client.post('/kyc/pan/verify', {pan_number: panNumber}),
};

/**
 * The generic "upload one file now, get a path back to link later" endpoint — see
 * UploadController's docblock on the backend. One file per request, so it fails (and
 * can be retried) independently of whatever else is picked, instead of riding along
 * in one large multipart request with everything else on the form. `file` is the
 * {uri, name, type} picker result; `type` is one of 'photo', 'pan_card', 'aadhaar',
 * 'rera_certificate', 'gst', 'cheque', 'signature' — whatever the caller is attaching.
 */
export const uploadApi = {
  upload: (file, type) =>
    client.post('/uploads', toFormData({file, type}), {
      headers: {'Content-Type': 'multipart/form-data'},
      timeout: UPLOAD_TIMEOUT_MS,
    }),
};

export const dashboardApi = {
  /** Developer headline figures + trend series. */
  fetch: () => client.get('/dashboard'),
};

// ---------------------------------------------------------------- catalogue
export const propertiesApi = {
  /** @param {{page?:number, per_page?:number, search?:string, sort?:string, direction?:string,
   *           developer_id?:number, type?:string, city?:string, price_min?:number, price_max?:number}} params */
  list: params => client.get('/properties', {params}),
  show: id => client.get(`/properties/${id}`),
  recordView: id => client.post(`/properties/${id}/view`),
  markInterested: id => client.post(`/properties/${id}/interest`),
};

export const developersApi = {
  list: params => client.get('/developers', {params}),
  show: id => client.get(`/developers/${id}`),
  properties: (id, params) => client.get(`/developers/${id}/properties`, {params}),
};

/**
 * The developer's own inventory — every project assigned to them, including ones
 * still awaiting their decision. Distinct from `developersApi.properties`, which is
 * the public view and only ever returns accepted + active listings.
 */
export const myPropertiesApi = {
  /** @param {{page?:number, per_page?:number, search?:string, developer_status?:string,
   *           status?:string, type?:string, city?:string, project_status?:string,
   *           sort?:string, direction?:string}} params */
  list: params => client.get('/my/properties', {params}),
  show: id => client.get(`/my/properties/${id}`),
  summary: () => client.get('/my/properties/summary'),
  /** Only the cities/types/stages present in this developer's own inventory. */
  filterOptions: () => client.get('/my/properties/filters'),
  /** Accepting is what makes the project visible to channel partners. */
  respond: (id, payload) => client.patch(`/my/properties/${id}/response`, payload),
};

export const leadsApi = {
  list: params => client.get('/leads', {params}),
  respond: (id, payload) => client.patch(`/leads/${id}`, payload),
};

// ---------------------------------------------------------------- push
export const pushApi = {
  registerDevice: payload => client.post('/auth/device-token', payload),
  /** Body on a DELETE — axios needs it under `data`, not as the second argument. */
  forgetDevice: payload => client.delete('/auth/device-token', {data: payload}),
};

// ---------------------------------------------------------------- channel partners
export const partnersApi = {
  /** @param {{page?:number, per_page?:number, search?:string, city?:string, state?:string,
   *           segment?:string, from?:string, to?:string, sort?:string, direction?:string}} params */
  list: params => client.get('/partners', {params}),
  show: brokerId => client.get(`/partners/${brokerId}`),
  /** The listings this partner was accepted on — paginated, a partner can have dozens. */
  projects: (brokerId, params) => client.get(`/partners/${brokerId}/projects`, {params}),
  /** Only the cities/states/segments present in this developer's own roster. */
  filterOptions: () => client.get('/partners/filters'),
};
