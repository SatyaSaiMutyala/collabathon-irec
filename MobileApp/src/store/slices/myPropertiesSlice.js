import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {myPropertiesApi} from '../../api/endpoints';
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
 * The developer's own inventory.
 *
 * Kept separate from `developersSlice.properties`, which holds the public listing of a
 * developer as a broker sees it. This one includes projects still awaiting the
 * developer's decision — the whole point of the screen is to act on those.
 */
const initialState = {
  list: initialListState(),
  // Keyed by property id, not one shared status/error — see propertiesSlice.js's
  // own detail state for why a single flag falsely flashed "Project not found"
  // whenever a second, different property was opened in the same session.
  detail: {byId: {}, statusById: {}, errorById: {}},
  summary: null,
  options: {cities: [], types: [], stages: []},
  optionsStatus: 'idle',
  respondStatus: 'idle', // idle | loading | succeeded | failed
  respondError: null,
};

export const fetchMyProperties = createAsyncThunk(
  'myProperties/fetch',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await myPropertiesApi.list({page, ...filters});
      return {...data, data: normalizeProperties(data.data)};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextMyProperties = createAsyncThunk(
  'myProperties/fetchNext',
  async (_, {getState, dispatch}) => {
    const {list} = getState().myProperties;
    if (!canLoadMore(list)) {
      return null;
    }
    return dispatch(fetchMyProperties({...list.params, page: list.page + 1})).unwrap();
  },
);

export const fetchMyProperty = createAsyncThunk(
  'myProperties/show',
  async (id, {rejectWithValue}) => {
    try {
      const {data} = await myPropertiesApi.show(id);
      return normalizeProperty(data.data);
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchMyPropertiesSummary = createAsyncThunk(
  'myProperties/summary',
  async (_, {rejectWithValue}) => {
    try {
      const {data} = await myPropertiesApi.summary();
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

/**
 * The values the filter drawer offers. Fetched once per mount rather than derived from
 * `list.items`: only one page is ever in memory, so deriving them would offer chips for
 * the current page and silently hide the rest of the inventory's cities.
 */
export const fetchMyPropertyFilters = createAsyncThunk(
  'myProperties/filters',
  async (_, {rejectWithValue}) => {
    try {
      const {data} = await myPropertiesApi.filterOptions();
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

/** Accept or decline an assignment. Accepting is what puts it in front of brokers. */
export const respondToProperty = createAsyncThunk(
  'myProperties/respond',
  async ({propertyId, status, reason}, {rejectWithValue}) => {
    try {
      const {data} = await myPropertiesApi.respond(propertyId, {status, reason});
      return {property: normalizeProperty(data.data), message: data.message};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

const myPropertiesSlice = createSlice({
  name: 'myProperties',
  initialState,
  reducers: {
    setFilters: (state, action) => {
      state.list.params = action.payload ?? {};
      state.list.page = 0;
    },
    clearRespondState: state => {
      state.respondStatus = 'idle';
      state.respondError = null;
    },
  },

  extraReducers: builder => {
    builder
      .addCase(fetchMyProperties.pending, (state, action) => {
        listPending(state.list, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.list.params = filters;
        }
      })
      .addCase(fetchMyProperties.fulfilled, (state, action) => {
        listFulfilled(state.list, action);
      })
      .addCase(fetchMyProperties.rejected, (state, action) => {
        listRejected(state.list, action);
      })

      .addCase(fetchMyProperty.pending, (state, action) => {
        state.detail.statusById[action.meta.arg] = 'loading';
        state.detail.errorById[action.meta.arg] = null;
      })
      .addCase(fetchMyProperty.fulfilled, (state, action) => {
        state.detail.statusById[action.payload.id] = 'succeeded';
        state.detail.byId[action.payload.id] = action.payload;
      })
      .addCase(fetchMyProperty.rejected, (state, action) => {
        state.detail.statusById[action.meta.arg] = 'failed';
        state.detail.errorById[action.meta.arg] = action.payload?.message ?? 'Could not load this project.';
      })

      .addCase(fetchMyPropertiesSummary.fulfilled, (state, action) => {
        state.summary = action.payload;
      })

      .addCase(fetchMyPropertyFilters.pending, state => {
        state.optionsStatus = 'loading';
      })
      .addCase(fetchMyPropertyFilters.fulfilled, (state, action) => {
        state.optionsStatus = 'succeeded';
        state.options = action.payload ?? initialState.options;
      })
      .addCase(fetchMyPropertyFilters.rejected, state => {
        // Leave the drawer usable: status and sort do not depend on this call, so a
        // failure here costs the city/type chips rather than the whole panel.
        state.optionsStatus = 'failed';
      })

      .addCase(respondToProperty.pending, state => {
        state.respondStatus = 'loading';
        state.respondError = null;
      })
      .addCase(respondToProperty.fulfilled, (state, action) => {
        const updated = action.payload.property;
        state.respondStatus = 'succeeded';

        // Patch both caches so the list badge and the detail footer agree immediately,
        // without a refetch.
        state.detail.byId[updated.id] = updated;

        const index = state.list.items.findIndex(item => item.id === updated.id);
        if (index !== -1) {
          state.list.items[index] = updated;
        }

        // The tab counts moved, so the cached summary is now wrong. Null it rather than
        // guessing the new numbers — the screen refetches it.
        state.summary = null;
      })
      .addCase(respondToProperty.rejected, (state, action) => {
        state.respondStatus = 'failed';
        state.respondError = action.payload?.message ?? 'Could not send your response.';
      });
  },
});

export const {setFilters, clearRespondState} = myPropertiesSlice.actions;

export const selectMyProperties = state => state.myProperties.list;
export const selectMyPropertyById = (state, id) => state.myProperties.detail.byId[id];
export const selectMyPropertyStatus = (state, id) => state.myProperties.detail.statusById[id] ?? 'idle';
export const selectMyPropertiesSummary = state => state.myProperties.summary;
export const selectMyPropertyOptions = state => state.myProperties.options;

export default myPropertiesSlice.reducer;
