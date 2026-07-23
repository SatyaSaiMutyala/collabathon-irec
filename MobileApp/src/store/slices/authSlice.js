import {createSlice} from '@reduxjs/toolkit';

const initialState = {
  role: null,
  registrationStatus: 'guest',
  isLoggedIn: false,
  broker: null,
  developer: null,
};

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setRole: (state, action) => {
      state.role = action.payload;
    },
    submitRegistration: (state, action) => {
      state.role = 'broker';
      state.broker = action.payload;
      state.registrationStatus = 'pendingApproval';
    },
    approveRegistration: state => {
      state.registrationStatus = 'approved';
    },
    rejectRegistration: state => {
      state.registrationStatus = 'rejected';
    },
    logIn: state => {
      state.role = 'broker';
      state.isLoggedIn = true;
    },
    logInAsDeveloper: (state, action) => {
      state.role = 'developer';
      state.developer = action.payload;
      state.isLoggedIn = true;
    },
    logOut: state => {
      state.isLoggedIn = false;
    },
    resetAuth: () => initialState,
    /** Restores a previously persisted session on app launch. */
    hydrateAuth: (_state, action) => action.payload,
  },
});

export const {
  setRole,
  submitRegistration,
  approveRegistration,
  rejectRegistration,
  logIn,
  logInAsDeveloper,
  logOut,
  resetAuth,
  hydrateAuth,
} = authSlice.actions;

export default authSlice.reducer;
