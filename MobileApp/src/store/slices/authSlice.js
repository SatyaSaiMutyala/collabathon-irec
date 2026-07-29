import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {authApi} from '../../api/endpoints';
import {extractError, setAuthToken} from '../../api/client';

/**
 * Auth against the Laravel API. Email + password is the login key for both mobile
 * roles; the mobile number is profile data only.
 *
 * `registrationStatus` mirrors the server's approval gate — a broker who registers
 * is `pending` and receives no token until an admin approves them.
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
};

// ---------------------------------------------------------------- thunks

export const registerBroker = createAsyncThunk(
  'auth/register',
  async (payload, {rejectWithValue}) => {
    try {
      const {data} = await authApi.register(payload);
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const login = createAsyncThunk(
  'auth/login',
  async ({email, password, role}, {rejectWithValue}) => {
    try {
      const {data} = await authApi.login({
        email,
        password,
        role,
        device_name: 'mobile',
      });
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
    // Best-effort: revoke server-side, but always clear locally.
    await authApi.logout();
  } catch {
    // A dead token or an offline device still signs out locally.
  }
  setAuthToken(null);
});

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
        state.user = action.payload;
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

      // ------------------------------------------------ me
      .addCase(fetchMe.fulfilled, (state, action) => {
        state.user = action.payload;
        state.role = action.payload.role;
      })

      // ------------------------------------------------ logout
      .addCase(logout.fulfilled, () => initialState);
  },
});

export const {setRole, clearAuthError, hydrateAuth, sessionExpired, resetAuth} =
  authSlice.actions;

export default authSlice.reducer;
