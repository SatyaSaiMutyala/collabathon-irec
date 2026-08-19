import {configureStore} from '@reduxjs/toolkit';
import authReducer, {sessionExpired} from './slices/authSlice';
import propertiesReducer from './slices/propertiesSlice';
import myPropertiesReducer from './slices/myPropertiesSlice';
import developersReducer from './slices/developersSlice';
import leadsReducer from './slices/leadsSlice';
import partnersReducer from './slices/partnersSlice';
import notificationsReducer from './slices/notificationsSlice';
import dashboardReducer from './slices/dashboardSlice';
import uiReducer from './slices/uiSlice';
import {saveAuthState} from './authPersistence';
import {setUnauthorizedHandler} from '../api/client';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    properties: propertiesReducer,
    myProperties: myPropertiesReducer,
    developers: developersReducer,
    leads: leadsReducer,
    partners: partnersReducer,
    notifications: notificationsReducer,
    dashboard: dashboardReducer,
    ui: uiReducer,
  },
});

/**
 * A 401 from any request means the token is dead — an admin may have rejected or
 * paused the account mid-session. Drop straight to the login screen rather than
 * letting screens retry against a credential that will never work again.
 */
setUnauthorizedHandler(() => {
  if (store.getState().auth.isLoggedIn) {
    store.dispatch(sessionExpired());
  }
});

/**
 * Persist only what a relaunch needs. Transient request state (status/error) is
 * deliberately excluded, so a failed login isn't restored as a failure next launch.
 */
let previousAuth = store.getState().auth;

store.subscribe(() => {
  const auth = store.getState().auth;
  if (auth === previousAuth) {
    return;
  }
  previousAuth = auth;

  saveAuthState({
    token: auth.token,
    user: auth.user,
    role: auth.role,
    registrationStatus: auth.registrationStatus,
    // Only meaningful for a 'draft' session, but harmless either way — a plain
    // relaunch (whether draft or not) has these before fetchMe even resolves, so a
    // draft resume never briefly renders step 1 before snapping to the real step.
    registrationStep: auth.registrationStep,
    draftProfile: auth.draftProfile,
    rejectionReason: auth.rejectionReason,
    isLoggedIn: auth.isLoggedIn,
  });
});
