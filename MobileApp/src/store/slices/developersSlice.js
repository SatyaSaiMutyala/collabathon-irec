import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {developersApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {normalizeProperties} from '../../api/normalizers';
import {
  canLoadMore,
  initialListState,
  listFulfilled,
  listPending,
  listRejected,
} from '../paginated';

/**
 * Developer directory, plus each developer's own paginated property list.
 * A developer's listings are never embedded in the parent payload — one developer
 * can have hundreds, which is exactly the unbounded response we must avoid.
 */

const initialState = {
  list: initialListState(),
  detail: {byId: {}, status: 'idle', error: null},
  // developerId -> paginated list of that developer's properties
  properties: {},
};

export const fetchDevelopers = createAsyncThunk(
  'developers/fetch',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await developersApi.list({page, ...filters});
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextDevelopers = createAsyncThunk(
  'developers/fetchNext',
  async (_, {getState, dispatch}) => {
    const {list} = getState().developers;
    if (!canLoadMore(list)) {
      return null;
    }
    return dispatch(fetchDevelopers({...list.params, page: list.page + 1})).unwrap();
  },
);

export const fetchDeveloper = createAsyncThunk(
  'developers/show',
  async (id, {rejectWithValue}) => {
    try {
      const {data} = await developersApi.show(id);
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchDeveloperProperties = createAsyncThunk(
  'developers/properties',
  async ({developerId, page = 1, ...filters}, {rejectWithValue}) => {
    try {
      const {data} = await developersApi.properties(developerId, {page, ...filters});
      return {developerId, ...data, data: normalizeProperties(data.data)};
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

const developersSlice = createSlice({
  name: 'developers',
  initialState,
  reducers: {
    setFilters: (state, action) => {
      state.list.params = action.payload ?? {};
      state.list.page = 0;
    },
  },

  extraReducers: builder => {
    builder
      .addCase(fetchDevelopers.pending, (state, action) => {
        listPending(state.list, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.list.params = filters;
        }
      })
      .addCase(fetchDevelopers.fulfilled, (state, action) => {
        listFulfilled(state.list, action);
      })
      .addCase(fetchDevelopers.rejected, (state, action) => {
        listRejected(state.list, action);
      })

      .addCase(fetchDeveloper.pending, state => {
        state.detail.status = 'loading';
        state.detail.error = null;
      })
      .addCase(fetchDeveloper.fulfilled, (state, action) => {
        state.detail.status = 'succeeded';
        state.detail.byId[action.payload.id] = action.payload;
      })
      .addCase(fetchDeveloper.rejected, (state, action) => {
        state.detail.status = 'failed';
        state.detail.error = action.payload?.message ?? 'Could not load this developer.';
      })

      // ------------------------------------------------ a developer's listings
      .addCase(fetchDeveloperProperties.pending, (state, action) => {
        const id = action.meta.arg.developerId;
        state.properties[id] = state.properties[id] ?? initialListState();
        listPending(state.properties[id], action);
      })
      .addCase(fetchDeveloperProperties.fulfilled, (state, action) => {
        const id = action.payload.developerId;
        state.properties[id] = state.properties[id] ?? initialListState();
        listFulfilled(state.properties[id], action);
      })
      .addCase(fetchDeveloperProperties.rejected, (state, action) => {
        const id = action.meta.arg.developerId;
        state.properties[id] = state.properties[id] ?? initialListState();
        listRejected(state.properties[id], action);
      });
  },
});

export const {setFilters} = developersSlice.actions;

export const selectDevelopers = state => state.developers.list.items;
export const selectDevelopersStatus = state => state.developers.list.status;
export const selectCanLoadMoreDevelopers = state => canLoadMore(state.developers.list);
export const selectDeveloperById = (state, id) => state.developers.detail.byId[id];
export const selectDeveloperProperties = (state, id) =>
  state.developers.properties[id] ?? initialListState();

export default developersSlice.reducer;
