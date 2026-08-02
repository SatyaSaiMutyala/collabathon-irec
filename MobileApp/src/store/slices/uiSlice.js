import {createSlice} from '@reduxjs/toolkit';

/**
 * Cross-cutting UI that no single screen owns.
 *
 * The snackbar lives here rather than in a React context so any thunk can raise one
 * without the screen that triggered it still being mounted — "Signed in" has to survive
 * the navigator swapping the auth stack out from under the login screen, and a message
 * raised from a slice's `rejected` case has no component to hang off at all.
 *
 * `token` changes on every show, even for an identical message, so the component can
 * restart its timer when the same text is raised twice.
 */
const initialState = {
  snackbar: null, // {message, tone, token}
};

let nextToken = 0;

const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    /** showSnackbar('Saved') or showSnackbar({message, tone}) — tone: neutral|success|danger */
    showSnackbar: {
      reducer: (state, action) => {
        state.snackbar = action.payload;
      },
      prepare: input => {
        const {message, tone = 'neutral'} =
          typeof input === 'string' ? {message: input} : input ?? {};

        nextToken += 1;

        return {payload: {message, tone, token: nextToken}};
      },
    },
    hideSnackbar: state => {
      state.snackbar = null;
    },
  },
});

export const {showSnackbar, hideSnackbar} = uiSlice.actions;

export const selectSnackbar = state => state.ui.snackbar;

export default uiSlice.reducer;
