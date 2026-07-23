import {configureStore} from '@reduxjs/toolkit';
import authReducer from './slices/authSlice';
import leadsReducer from './slices/leadsSlice';
import developerLeadsReducer from './slices/developerLeadsSlice';
import {saveAuthState} from './authPersistence';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    leads: leadsReducer,
    developerLeads: developerLeadsReducer,
  },
});

// Auto-persist the session (registration status, login state, broker profile)
// so a logged-in broker doesn't have to log back in on every app launch.
let previousAuth = store.getState().auth;
store.subscribe(() => {
  const auth = store.getState().auth;
  if (auth !== previousAuth) {
    previousAuth = auth;
    saveAuthState(auth);
  }
});
