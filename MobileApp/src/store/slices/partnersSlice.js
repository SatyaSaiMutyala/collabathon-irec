import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {partnersApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {
  canLoadMore,
  initialListState,
  listFulfilled,
  listPending,
  listRejected,
} from '../paginated';

/**
 * The developer's channel partners — brokers they have accepted, one row per broker
 * rather than per lead.
 *
 * Search and every filter are query parameters on the request, never a pass over
 * `items`: the list is a page of a roster that runs to thousands of rows, so filtering
 * what happens to be loaded would quietly answer the wrong question.
 */

const initialState = {
  list: initialListState(),
  /** Cities/states/segments present in this roster, for the filter controls. */
  options: {cities: [], states: [], segments: []},
  optionsStatus: 'idle',
  /**
   * Accepted projects per broker, keyed by broker id. Kept out of the partner rows so a
   * roster refresh does not throw away a detail screen's already-loaded pages.
   */
  projectsByBroker: {},
};

export const fetchPartners = createAsyncThunk(
  'partners/fetch',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await partnersApi.list({page, ...filters});
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextPartners = createAsyncThunk(
  'partners/fetchNext',
  async (_, {getState, dispatch}) => {
    const {list} = getState().partners;
    if (!canLoadMore(list)) {
      return null;
    }
    // `list.params` carries the active search/filters, so paging never drops them.
    return dispatch(fetchPartners({...list.params, page: list.page + 1})).unwrap();
  },
);

export const fetchPartnerFilters = createAsyncThunk(
  'partners/filters',
  async (_, {rejectWithValue}) => {
    try {
      const {data} = await partnersApi.filterOptions();
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

/** The listings one partner has been accepted on. Paginated — a partner can have dozens. */
export const fetchPartnerProjects = createAsyncThunk(
  'partners/projects',
  async ({brokerId, page = 1}, {rejectWithValue}) => {
    try {
      const {data} = await partnersApi.projects(brokerId, {page});
      return {brokerId, ...data};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextPartnerProjects = createAsyncThunk(
  'partners/projectsNext',
  async ({brokerId}, {getState, dispatch}) => {
    const list = getState().partners.projectsByBroker[brokerId];
    if (!list || !canLoadMore(list)) {
      return null;
    }
    return dispatch(fetchPartnerProjects({brokerId, page: list.page + 1})).unwrap();
  },
);

const partnersSlice = createSlice({
  name: 'partners',
  initialState,
  reducers: {
    clearPartners: state => {
      state.list = initialListState();
    },
  },

  extraReducers: builder => {
    builder
      .addCase(fetchPartners.pending, (state, action) => {
        listPending(state.list, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.list.params = filters;
        }
      })
      .addCase(fetchPartners.fulfilled, (state, action) => {
        listFulfilled(state.list, action);
      })
      .addCase(fetchPartners.rejected, (state, action) => {
        listRejected(state.list, action);
      })

      .addCase(fetchPartnerFilters.pending, state => {
        state.optionsStatus = 'loading';
      })
      .addCase(fetchPartnerFilters.fulfilled, (state, action) => {
        state.optionsStatus = 'succeeded';
        state.options = action.payload ?? initialState.options;
      })
      .addCase(fetchPartnerFilters.rejected, state => {
        // A missing option list is not worth an error state — the controls just render
        // empty and search still works.
        state.optionsStatus = 'failed';
      })

      .addCase(fetchPartnerProjects.pending, (state, action) => {
        const {brokerId} = action.meta.arg;
        state.projectsByBroker[brokerId] ??= initialListState();
        listPending(state.projectsByBroker[brokerId], action);
      })
      .addCase(fetchPartnerProjects.fulfilled, (state, action) => {
        listFulfilled(state.projectsByBroker[action.payload.brokerId], action);
      })
      .addCase(fetchPartnerProjects.rejected, (state, action) => {
        const {brokerId} = action.meta.arg;
        state.projectsByBroker[brokerId] ??= initialListState();
        listRejected(state.projectsByBroker[brokerId], action);
      });
  },
});

export const {clearPartners} = partnersSlice.actions;

export const selectPartners = state => state.partners.list.items;
export const selectPartnersList = state => state.partners.list;
export const selectPartnerOptions = state => state.partners.options;

/** Backs the detail screen when it is opened from the partner list rather than a lead. */
export const selectPartnerById = (state, brokerId) =>
  state.partners.list.items.find(partner => partner.id === brokerId);

export const selectPartnerProjects = (state, brokerId) =>
  state.partners.projectsByBroker[brokerId];

export default partnersSlice.reducer;
