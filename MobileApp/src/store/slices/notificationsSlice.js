import {createSlice} from '@reduxjs/toolkit';

const initialState = {
  /** {[notificationId]: true} — marked once the Notifications screen has shown them. */
  readIds: {},
};

const notificationsSlice = createSlice({
  name: 'notifications',
  initialState,
  reducers: {
    markAllRead: (state, action) => {
      action.payload.forEach(id => {
        state.readIds[id] = true;
      });
    },
  },
});

export const {markAllRead} = notificationsSlice.actions;
export default notificationsSlice.reducer;
