import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {notificationsApi} from '../../api/endpoints';
import {extractError} from '../../api/client';

/**
 * The Notifications screen's own state: admin broadcasts fetched from the server, plus
 * the read marks for every row on the screen — including the lead-derived ones, which
 * are built in useNotifications and have no server-side read state of their own.
 */

const initialState = {
  /** Admin broadcasts — see NotificationController. */
  announcements: [],
  status: 'idle',
  error: null,
  /** {[notificationId]: true} — marked once the Notifications screen has shown them. */
  readIds: {},
};

export const fetchAnnouncements = createAsyncThunk(
  'notifications/fetchAnnouncements',
  async (_, {rejectWithValue}) => {
    try {
      const {data} = await notificationsApi.fetch();
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

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
  extraReducers: builder => {
    builder
      .addCase(fetchAnnouncements.pending, state => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(fetchAnnouncements.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.announcements = action.payload;
      })
      // A failure here leaves whatever was already fetched in place: the feed's other
      // half comes from leads and still renders, so emptying this would turn one dead
      // request into a screen that looks like nothing was ever sent.
      .addCase(fetchAnnouncements.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload;
      });
  },
});

export const {markAllRead} = notificationsSlice.actions;
export default notificationsSlice.reducer;
