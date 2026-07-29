import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {dashboardApi} from '../../api/endpoints';
import {extractError} from '../../api/client';

/** Developer dashboard aggregates — all computed server-side in SQL. */
const initialState = {
  data: null,
  status: 'idle', // idle | loading | succeeded | failed
  error: null,
};

export const fetchDashboard = createAsyncThunk(
  'dashboard/fetch',
  async (_, {rejectWithValue}) => {
    try {
      const {data} = await dashboardApi.fetch();
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

const dashboardSlice = createSlice({
  name: 'dashboard',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder
      .addCase(fetchDashboard.pending, state => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(fetchDashboard.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.data = action.payload;
      })
      .addCase(fetchDashboard.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload?.message ?? 'Could not load your dashboard.';
      });
  },
});

export default dashboardSlice.reducer;
