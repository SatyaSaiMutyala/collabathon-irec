import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {propertiesApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {normalizeProperties, normalizeProperty} from '../../api/normalizers';
import {
  canLoadMore,
  initialListState,
  listFulfilled,
  listPending,
  listRejected,
} from '../paginated';

/**
 * Property catalogue. Search and filters are sent to the server — the list here
 * only ever holds the pages that have actually been fetched.
 */

const initialState = {
  list: initialListState(),
  detail: {byId: {}, status: 'idle', error: null},
  interestStatus: 'idle', // idle | loading | succeeded | failed
  interestError: null,
};

// ---------------------------------------------------------------- thunks

export const fetchProperties = createAsyncThunk(
  'properties/fetch',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await propertiesApi.list({page, ...filters});
      return {...data, data: normalizeProperties(data.data)};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

/** Loads the next page if one exists; a no-op otherwise. */
export const fetchNextProperties = createAsyncThunk(
  'properties/fetchNext',
  async (_, {getState, dispatch}) => {
    const {list} = getState().properties;
    if (!canLoadMore(list)) {
      return null;
    }
    return dispatch(fetchProperties({...list.params, page: list.page + 1})).unwrap();
  },
);

export const fetchProperty = createAsyncThunk(
  'properties/show',
  async (id, {rejectWithValue}) => {
    try {
      const {data} = await propertiesApi.show(id);
      // Recording the view is fire-and-forget: it must never block the detail screen.
      propertiesApi.recordView(id).catch(() => {});
      return normalizeProperty(data.data);
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

/** The moment the broker's contact details unlock for the developer. */
export const markInterested = createAsyncThunk(
  'properties/interest',
  async (id, {rejectWithValue}) => {
    try {
      const {data} = await propertiesApi.markInterested(id);
      return {propertyId: id, lead: data.data};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

// ---------------------------------------------------------------- slice

const propertiesSlice = createSlice({
  name: 'properties',
  initialState,
  reducers: {
    /** Replaces the active search/filters and resets to page 1. */
    setFilters: (state, action) => {
      state.list.params = action.payload ?? {};
      state.list.page = 0;
    },
    clearInterestState: state => {
      state.interestStatus = 'idle';
      state.interestError = null;
    },
  },

  extraReducers: builder => {
    builder
      .addCase(fetchProperties.pending, (state, action) => {
        listPending(state.list, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.list.params = filters;
        }
      })
      .addCase(fetchProperties.fulfilled, (state, action) => {
        listFulfilled(state.list, action);
      })
      .addCase(fetchProperties.rejected, (state, action) => {
        listRejected(state.list, action);
      })

      // ------------------------------------------------ detail
      .addCase(fetchProperty.pending, state => {
        state.detail.status = 'loading';
        state.detail.error = null;
      })
      .addCase(fetchProperty.fulfilled, (state, action) => {
        state.detail.status = 'succeeded';
        state.detail.byId[action.payload.id] = action.payload;
      })
      .addCase(fetchProperty.rejected, (state, action) => {
        state.detail.status = 'failed';
        state.detail.error = action.payload?.message ?? 'Could not load this property.';
      })

      // ------------------------------------------------ interest
      .addCase(markInterested.pending, state => {
        state.interestStatus = 'loading';
        state.interestError = null;
      })
      .addCase(markInterested.fulfilled, (state, action) => {
        const {propertyId, lead} = action.payload;
        state.interestStatus = 'succeeded';

        // Reflect the new lead in both the list row and the cached detail, so the
        // card shows "Interested" without a refetch. Field names here are the
        // normalized ones (interestsCount), not the raw API's interests_count.
        const row = state.list.items.find(item => item.id === propertyId);
        if (row) {
          row.my_lead = lead;
          row.interestsCount = (row.interestsCount ?? 0) + 1;
        }

        const detail = state.detail.byId[propertyId];
        if (detail) {
          detail.my_lead = lead;
          detail.interestsCount = (detail.interestsCount ?? 0) + 1;
        }
      })
      .addCase(markInterested.rejected, (state, action) => {
        state.interestStatus = 'failed';
        state.interestError = action.payload?.message ?? 'Could not record your interest.';
      });
  },
});

export const {setFilters, clearInterestState} = propertiesSlice.actions;

// ---------------------------------------------------------------- selectors
export const selectProperties = state => state.properties.list.items;
export const selectPropertiesStatus = state => state.properties.list.status;
export const selectPropertiesError = state => state.properties.list.error;
export const selectCanLoadMoreProperties = state => canLoadMore(state.properties.list);
export const selectPropertyById = (state, id) => state.properties.detail.byId[id];

export default propertiesSlice.reducer;
