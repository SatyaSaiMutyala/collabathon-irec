import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {authApi} from '../../api/endpoints';
import {extractError, setAuthToken} from '../../api/client';
// uiSlice imports nothing back, so this stays a one-way edge — no require cycle.
import {showSnackbar} from './uiSlice';
import {unregisterDevice} from '../../services/push';

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
 */

const initialState = {
  token: null,
  user: null,
  role: null, // 'broker' | 'developer'
  registrationStatus: 'guest', // guest | pendingApproval | rejected | approved
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

export const registerBroker = createAsyncThunk(
  'auth/register',
  async (payload, {rejectWithValue}) => {
    try {
      const {data} = await authApi.register(payload);
      // The server's own confirmation wording travels with the user, so the screen shows
      // what the API actually said rather than a copy of it that can drift.
      return {user: data.data, message: data.message};
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
        device_name: 'mobile',
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
  },

  extraReducers: builder => {
    builder
      // ------------------------------------------------ register
      .addCase(registerBroker.pending, state => {
        state.status = 'loading';
        state.error = null;
        state.fieldErrors = {};
      })
      .addCase(registerBroker.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.role = 'broker';
        state.user = action.payload.user;
        // No token is issued until an admin approves.
        state.registrationStatus = 'pendingApproval';
        state.isLoggedIn = false;
      })
      .addCase(registerBroker.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message ?? 'Registration failed.';
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

        // Only the 'login' branch changes session state — 'register' hands the
        // screen a verification_token to carry forward on its own (see the thunk),
        // and there is nothing yet to consider "signed in" about it.
        if (payload.status === 'login') {
          const {token, data} = payload;
          setAuthToken(token);

          state.token = token;
          state.user = data;
          state.role = data.role;
          state.isLoggedIn = true;
          state.registrationStatus = 'approved';
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

        // Only the 'login' branch changes session state — 'register' hands the
        // screen the verified email to carry forward via navigation params.
        if (payload.status === 'login') {
          const {token, data} = payload;
          setAuthToken(token);

          state.token = token;
          state.user = data;
          state.role = data.role;
          state.isLoggedIn = true;
          state.registrationStatus = 'approved';
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
} = authSlice.actions;

export default authSlice.reducer;
