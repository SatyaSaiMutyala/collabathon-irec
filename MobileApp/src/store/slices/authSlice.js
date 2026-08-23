import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {authApi} from '../../api/endpoints';
import {extractError, setAuthToken} from '../../api/client';
// uiSlice imports nothing back, so this stays a one-way edge — no require cycle.
import {showSnackbar} from './uiSlice';
import {unregisterDevice} from '../../services/push';
import {deviceName} from '../../utils/device';

/**
 * Auth against the Laravel API — two different mechanisms for two different roles.
 * Developers sign in with email + password (`login`, admin-provisioned accounts).
 * Channel partners sign in with email + a 4-digit OTP (`sendEmailOtp` then
 * `verifyEmailOtp`) — there is no password for this role at all, on either side of
 * the API. (An earlier mobile-number + OTP path — `sendOtp`/`verifyOtp` — still
 * lives here too; see AuthNavigator for why it's kept but unlinked.)
 *
 * `registrationStatus` mirrors the server's approval gate — a broker who registers
 * is `pending` and receives no token until an admin approves them. That gate applies
 * the same way whichever door was used, so `verifyOtp`/`verifyEmailOtp` can land on
 * it too, not just `login`.
 *
 * Registration itself is 3 steps (Personal -> Professional -> Business), each saved
 * to the database as it's completed rather than all at once — `startRegistration`
 * (step 1) creates the account as `registrationStatus: 'draft'` and issues a token,
 * `saveRegistrationStep` (steps 2-3, and Save Draft on any step) reuses it. A `draft`
 * session persists and resumes exactly like an approved one does — see
 * RootNavigator, which opens straight to CompleteProfileScreen at `registrationStep`
 * for this status instead of the broker/developer stacks.
 */

const initialState = {
  token: null,
  user: null,
  role: null, // 'broker' | 'developer'
  registrationStatus: 'guest', // guest | draft | pendingApproval | rejected | approved
  // The furthest of the 3 wizard steps reached so far — only meaningful alongside
  // registrationStatus === 'draft'. 1 until a step-1 save says otherwise.
  registrationStep: 1,
  // The broker_profile fields already saved for an in-progress registration, exactly
  // as UserResource::draftProfile() shapes them — CompleteProfileScreen seeds its form
  // from this when resuming rather than starting blank.
  draftProfile: null,
  // Set only for a draft that exists because an admin rejected it — verifyOtp/
  // verifyEmailOtp drop a rejected broker straight back into `draft` rather than a
  // dead end, and this is why: CompleteProfileScreen shows it so the broker knows
  // what to fix before resubmitting. Null for an ordinary in-progress registration.
  rejectionReason: null,
  isLoggedIn: false,
  status: 'idle', // idle | loading | succeeded | failed
  error: null,
  fieldErrors: {},

  // The OTP challenge in flight, if any. Deliberately holds only what a screen needs
  // to render itself (mobile, timing, the last error) — never the verification_token
  // a successful 'register' result carries, which stays a one-time hand-off between
  // screens via navigation params instead of living in (and being persisted by)
  // Redux state.
  otp: {
    mobile: '',
    status: 'idle', // idle | sending | sent | verifying | error
    error: null,
    expiresIn: null,
    debugCode: null, // dev-only, only ever present when the API's own env is local
  },

  // The email-OTP challenge in flight — same shape as `otp` above, kept separate so
  // a screen reading one can't be stepped on by state changes belonging to the other.
  emailOtp: {
    email: '',
    status: 'idle', // idle | sending | sent | verifying | error
    error: null,
    expiresIn: null,
    debugCode: null,
  },
};

// ---------------------------------------------------------------- thunks

/**
 * Step 1 (Personal info) — the only step that isn't already authenticated, since
 * nothing exists server-side yet for a token to belong to. Fulfilling this hydrates
 * the session exactly like `login` does (see the reducer below): from this point on,
 * every later step is a normal authenticated `saveRegistrationStep` call.
 */
export const startRegistration = createAsyncThunk(
  'auth/startRegistration',
  async (payload, {rejectWithValue}) => {
    try {
      const {data} = await authApi.startRegistration(payload);
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

/**
 * Steps 2-3, and Save Draft on any step. `payload.save_draft` (boolean) and
 * `payload.step` (2|3) travel straight through to the API — see
 * AuthController::saveRegistrationStep for what each combination does server-side.
 */
export const saveRegistrationStep = createAsyncThunk(
  'auth/saveRegistrationStep',
  async (payload, {rejectWithValue}) => {
    try {
      const {data} = await authApi.saveRegistrationStep(payload);
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const login = createAsyncThunk(
  'auth/login',
  async ({email, password, role}, {dispatch, rejectWithValue}) => {
    try {
      const {data} = await authApi.login({
        email,
        password,
        role,
        device_name: deviceName(),
      });

      // Raised here rather than in the screen: RootNavigator swaps the auth stack out
      // the moment isLoggedIn flips, so the component that asked for the login is gone
      // before it could show anything.
      dispatch(showSnackbar({message: `Welcome back, ${data.data?.name ?? ''}`.trim(), tone: 'success'}));

      return data;
    } catch (error) {
      const normalised = extractError(error);
      // 403 is the approval gate, not a credential failure — the UI routes the
      // broker to the "pending approval" screen instead of showing an error.
      if (normalised.status === 403) {
        normalised.pendingApproval = true;
      }
      return rejectWithValue(normalised);
    }
  },
);

// ---------------------------------------------------------------- channel-partner OTP

export const sendOtp = createAsyncThunk(
  'auth/sendOtp',
  async (mobile, {rejectWithValue}) => {
    try {
      const {data} = await authApi.sendOtp(mobile);
      return {mobile, ...data};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const verifyOtp = createAsyncThunk(
  'auth/verifyOtp',
  async ({mobile, code}, {dispatch, rejectWithValue}) => {
    try {
      const {data} = await authApi.verifyOtp({mobile, code});

      if (data.status === 'login') {
        dispatch(
          showSnackbar({
            message: `Welcome back, ${data.data?.name ?? ''}`.trim(),
            tone: 'success',
          }),
        );
      }

      return data;
    } catch (error) {
      const normalised = extractError(error);

      // The approval gate answers 403 with its own {status: 'pending'|'rejected'} in
      // the body — kept here under a distinct key rather than overwriting
      // `normalised.status` (that one is the *HTTP* status, 403, which `login`'s
      // rejected-branch above already reads for the same purpose).
      if (normalised.status === 403) {
        normalised.pendingApproval = true;
        normalised.approvalStatus = error.response?.data?.status;
      }

      return rejectWithValue(normalised);
    }
  },
);

// ---------------------------------------------------------------- channel-partner email OTP

export const sendEmailOtp = createAsyncThunk(
  'auth/sendEmailOtp',
  async (email, {rejectWithValue}) => {
    try {
      const {data} = await authApi.sendEmailOtp(email);
      return {email, ...data};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const verifyEmailOtp = createAsyncThunk(
  'auth/verifyEmailOtp',
  async ({email, code}, {dispatch, rejectWithValue}) => {
    try {
      const {data} = await authApi.verifyEmailOtp({email, code});

      if (data.status === 'login') {
        dispatch(
          showSnackbar({
            message: `Welcome back, ${data.data?.name ?? ''}`.trim(),
            tone: 'success',
          }),
        );
      }

      return data;
    } catch (error) {
      const normalised = extractError(error);

      // Same approval-gate shape as `verifyOtp`'s rejected branch above.
      if (normalised.status === 403) {
        normalised.pendingApproval = true;
        normalised.approvalStatus = error.response?.data?.status;
      }

      return rejectWithValue(normalised);
    }
  },
);

export const fetchMe = createAsyncThunk('auth/me', async (_, {rejectWithValue}) => {
  try {
    const {data} = await authApi.me();
    return data.data;
  } catch (error) {
    return rejectWithValue(extractError(error));
  }
});

export const logout = createAsyncThunk('auth/logout', async () => {
  try {
    // Before the Sanctum token is revoked — this call needs it. Otherwise the row is
    // left behind and the next person to sign in on this handset inherits the pushes.
    await unregisterDevice();
    // Best-effort: revoke server-side, but always clear locally.
    await authApi.logout();
  } catch {
    // A dead token or an offline device still signs out locally.
  }
  setAuthToken(null);
});

/**
 * Self-service account deletion, from the Profile screen. Unlike `logout` — which is
 * harmless either way and always clears locally even if the server call fails — this
 * is destructive and must not be treated as done unless the server actually did it:
 * a failed request leaves the session untouched so the screen can show the error and
 * let the user retry, rather than signing them out of an account that still exists.
 */
export const deleteAccount = createAsyncThunk(
  'auth/deleteAccount',
  async (_, {rejectWithValue}) => {
    try {
      const {data} = await authApi.deleteAccount();

      try {
        // Before the token is cleared locally — same ordering as logout(), and for
        // the same reason: this call needs it, even though the server already
        // revoked it server-side by this point.
        await unregisterDevice();
      } catch {
        // Best-effort — the account is deleted server-side regardless.
      }
      setAuthToken(null);

      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

// ---------------------------------------------------------------- slice

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setRole: (state, action) => {
      state.role = action.payload;
    },
    clearAuthError: state => {
      state.error = null;
      state.fieldErrors = {};
      state.status = 'idle';
    },
    /** Restores a persisted session on launch and re-arms the HTTP client. */
    hydrateAuth: (state, action) => {
      const persisted = action.payload ?? {};
      setAuthToken(persisted.token ?? null);
      return {...initialState, ...persisted, status: 'idle', error: null, fieldErrors: {}};
    },
    /** Called by the 401 interceptor — the token is gone or was revoked. */
    sessionExpired: () => {
      setAuthToken(null);
      return {...initialState, error: 'Your session has expired. Please sign in again.'};
    },
    resetAuth: () => {
      setAuthToken(null);
      return initialState;
    },
    /** Mount-time reset for the mobile-number screen — no stale error/mobile from a
     *  previous attempt bleeding into a fresh one. */
    resetOtp: state => {
      state.otp = initialState.otp;
    },
    /** Same, for the email-OTP screen. */
    resetEmailOtp: state => {
      state.emailOtp = initialState.emailOtp;
    },
    /**
     * "Start Over" on CompleteProfileScreen — wipes what the wizard has *shown so
     * far* back to a blank step 1, purely in this client. No thunk, no request:
     * the server's actual saved draft is untouched and reappears exactly as it was
     * if the broker closes the app without saving anything new, which is the
     * point — this is a local "let me retype it" reset, not a delete.
     *
     * `verified_channel` survives the wipe — it says which of mobile/email this
     * broker actually proved with an OTP (see CompleteProfileScreen's own
     * `verifiedChannel` derivation), a fact about how they authenticated, not
     * draft content. A resumed session (no route params) reads it off this same
     * object, so clearing it here would silently unlock whichever field it was
     * meant to keep locked.
     */
    resetDraftLocally: state => {
      state.registrationStep = 1;
      state.draftProfile = state.draftProfile
        ? {verified_channel: state.draftProfile.verified_channel}
        : null;
    },
  },

  extraReducers: builder => {
    builder
      // ------------------------------------------------ registration wizard
      .addCase(startRegistration.pending, state => {
        state.status = 'loading';
        state.error = null;
        state.fieldErrors = {};
      })
      .addCase(startRegistration.fulfilled, (state, action) => {
        const {token, data} = action.payload;
        setAuthToken(token);

        state.status = 'succeeded';
        state.token = token;
        state.user = data;
        state.role = 'broker';
        state.isLoggedIn = true;
        state.registrationStatus = 'draft';
        state.registrationStep = data.registration_step ?? 1;
        state.draftProfile = data.draft_profile ?? null;
        state.rejectionReason = null;
        state.error = null;
      })
      .addCase(startRegistration.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message ?? 'Could not start registration.';
        state.fieldErrors = action.payload?.errors ?? {};
      })

      .addCase(saveRegistrationStep.pending, state => {
        state.status = 'loading';
        state.error = null;
        state.fieldErrors = {};
      })
      .addCase(saveRegistrationStep.fulfilled, (state, action) => {
        const {data} = action.payload;
        state.status = 'succeeded';
        state.user = data;
        state.error = null;

        // Step 3's real submit (not Save Draft) finalizes: the account is `pending`
        // now, UserResource no longer sends registration_step/draft_profile at all,
        // and there's no session left to keep signed into — same as registerBroker
        // used to do, no token was ever issued for this status either.
        if (data.status === 'pending') {
          setAuthToken(null);
          state.token = null;
          state.isLoggedIn = false;
          state.registrationStatus = 'pendingApproval';
          state.draftProfile = null;
          state.rejectionReason = null;
          return;
        }

        state.registrationStep = data.registration_step ?? state.registrationStep;
        state.draftProfile = data.draft_profile ?? state.draftProfile;
        state.rejectionReason = data.rejection_reason ?? state.rejectionReason;
      })
      .addCase(saveRegistrationStep.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message ?? 'Could not save this step.';
        state.fieldErrors = action.payload?.errors ?? {};
      })

      // ------------------------------------------------ login
      .addCase(login.pending, state => {
        state.status = 'loading';
        state.error = null;
        state.fieldErrors = {};
      })
      .addCase(login.fulfilled, (state, action) => {
        const {token, data} = action.payload;
        setAuthToken(token);

        state.status = 'succeeded';
        state.token = token;
        state.user = data;
        state.role = data.role;
        state.isLoggedIn = true;
        state.registrationStatus = 'approved';
        state.error = null;
      })
      .addCase(login.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message ?? 'Sign in failed.';
        state.fieldErrors = action.payload?.errors ?? {};

        if (action.payload?.pendingApproval) {
          state.registrationStatus =
            action.payload?.status === 'rejected' ? 'rejected' : 'pendingApproval';
        }
      })

      // ------------------------------------------------ channel-partner OTP
      .addCase(sendOtp.pending, (state, action) => {
        state.otp.status = 'sending';
        state.otp.mobile = action.meta.arg;
        state.otp.error = null;
      })
      .addCase(sendOtp.fulfilled, (state, action) => {
        state.otp.status = 'sent';
        state.otp.mobile = action.payload.mobile;
        state.otp.expiresIn = action.payload.expires_in ?? null;
        state.otp.debugCode = action.payload.debug_code ?? null;
      })
      .addCase(sendOtp.rejected, (state, action) => {
        state.otp.status = 'error';
        state.otp.error = action.payload?.message ?? 'Could not send the code.';
      })

      .addCase(verifyOtp.pending, state => {
        state.otp.status = 'verifying';
        state.otp.error = null;
      })
      .addCase(verifyOtp.fulfilled, (state, action) => {
        const payload = action.payload;
        state.otp.status = 'idle';
        state.otp.error = null;

        // 'register' hands the screen a verification_token to carry forward on its
        // own (see the thunk) — nothing to consider "signed in" about it yet.
        if (payload.status === 'login' || payload.status === 'draft') {
          const {token, data} = payload;
          setAuthToken(token);

          state.token = token;
          state.user = data;
          state.role = data.role;
          state.isLoggedIn = true;
          state.registrationStatus = payload.status === 'draft' ? 'draft' : 'approved';
          state.registrationStep = data.registration_step ?? state.registrationStep;
          state.draftProfile = data.draft_profile ?? null;
          state.rejectionReason = data.rejection_reason ?? null;
        }
      })
      .addCase(verifyOtp.rejected, (state, action) => {
        state.otp.status = 'error';
        state.otp.error = action.payload?.message ?? 'Verification failed.';

        if (action.payload?.pendingApproval) {
          // Only a broker ever reaches this branch — developers don't call
          // verifyOtp — so this is safe to set unconditionally. PendingApprovalScreen
          // reads it to decide which sign-in screen "back" should return to.
          state.role = 'broker';
          state.registrationStatus =
            action.payload?.approvalStatus === 'rejected' ? 'rejected' : 'pendingApproval';
        }
      })

      // ------------------------------------------------ channel-partner email OTP
      .addCase(sendEmailOtp.pending, (state, action) => {
        state.emailOtp.status = 'sending';
        state.emailOtp.email = action.meta.arg;
        state.emailOtp.error = null;
      })
      .addCase(sendEmailOtp.fulfilled, (state, action) => {
        state.emailOtp.status = 'sent';
        state.emailOtp.email = action.payload.email;
        state.emailOtp.expiresIn = action.payload.expires_in ?? null;
        state.emailOtp.debugCode = action.payload.debug_code ?? null;
      })
      .addCase(sendEmailOtp.rejected, (state, action) => {
        state.emailOtp.status = 'error';
        state.emailOtp.error = action.payload?.message ?? 'Could not send the code.';
      })

      .addCase(verifyEmailOtp.pending, state => {
        state.emailOtp.status = 'verifying';
        state.emailOtp.error = null;
      })
      .addCase(verifyEmailOtp.fulfilled, (state, action) => {
        const payload = action.payload;
        state.emailOtp.status = 'idle';
        state.emailOtp.error = null;

        // 'register' hands the screen the verified email to carry forward via
        // navigation params — nothing to consider "signed in" about it yet.
        if (payload.status === 'login' || payload.status === 'draft') {
          const {token, data} = payload;
          setAuthToken(token);

          state.token = token;
          state.user = data;
          state.role = data.role;
          state.isLoggedIn = true;
          state.registrationStatus = payload.status === 'draft' ? 'draft' : 'approved';
          state.registrationStep = data.registration_step ?? state.registrationStep;
          state.draftProfile = data.draft_profile ?? null;
          state.rejectionReason = data.rejection_reason ?? null;
        }
      })
      .addCase(verifyEmailOtp.rejected, (state, action) => {
        state.emailOtp.status = 'error';
        state.emailOtp.error = action.payload?.message ?? 'Verification failed.';

        if (action.payload?.pendingApproval) {
          state.role = 'broker';
          state.registrationStatus =
            action.payload?.approvalStatus === 'rejected' ? 'rejected' : 'pendingApproval';
        }
      })

      // ------------------------------------------------ me
      .addCase(fetchMe.fulfilled, (state, action) => {
        state.user = action.payload;
        state.role = action.payload.role;

        // A plain app-reopen (token already valid, no fresh OTP verify) needs this
        // too — otherwise only the moment right after a live step save reflects a
        // draft session correctly, and a returning `draft` user's persisted
        // registrationStatus (which loadAuthState restores before this thunk even
        // resolves) would go stale the instant they reach step 2 or 3 on some other
        // device/reinstall.
        if (action.payload.status === 'draft') {
          state.registrationStatus = 'draft';
          state.registrationStep = action.payload.registration_step ?? state.registrationStep;
          state.draftProfile = action.payload.draft_profile ?? state.draftProfile;
          state.rejectionReason = action.payload.rejection_reason ?? state.rejectionReason;
        }
      })

      // ------------------------------------------------ logout
      .addCase(logout.fulfilled, () => initialState)

      // ------------------------------------------------ delete account
      .addCase(deleteAccount.pending, state => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(deleteAccount.fulfilled, () => initialState)
      .addCase(deleteAccount.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message ?? 'Could not delete your account. Please try again.';
      });
  },
});

export const {
  setRole,
  clearAuthError,
  hydrateAuth,
  sessionExpired,
  resetAuth,
  resetOtp,
  resetEmailOtp,
  resetDraftLocally,
} = authSlice.actions;

export default authSlice.reducer;
