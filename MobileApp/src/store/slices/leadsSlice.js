import {createAsyncThunk, createSlice} from '@reduxjs/toolkit';
import {leadsApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {
  canLoadMore,
  initialListState,
  listFulfilled,
  listPending,
  listRejected,
} from '../paginated';

/**
 * One slice for both roles — /api/v1/leads is scoped server-side by the token, so a
 * broker gets their own leads and a developer gets their inbox from the same call.
 *
 * Broker contact details are absent from the payload until the lead unlocks them;
 * the client never has to hide anything, because it was never sent.
 */

const initialState = {
  list: initialListState(),
  respondStatus: 'idle', // idle | loading | succeeded | failed
  respondError: null,
};

export const fetchLeads = createAsyncThunk(
  'leads/fetch',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await leadsApi.list({page, ...filters});
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextLeads = createAsyncThunk(
  'leads/fetchNext',
  async (_, {getState, dispatch}) => {
    const {list} = getState().leads;
    if (!canLoadMore(list)) {
      return null;
    }
    return dispatch(fetchLeads({...list.params, page: list.page + 1})).unwrap();
  },
);

/** Developer accepts or declines an interested broker. */
export const respondToLead = createAsyncThunk(
  'leads/respond',
  async ({leadId, status, note}, {rejectWithValue}) => {
    try {
      const {data} = await leadsApi.respond(leadId, {status, developer_note: note});
      return data.data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

const leadsSlice = createSlice({
  name: 'leads',
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
      .addCase(fetchLeads.pending, (state, action) => {
        listPending(state.list, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.list.params = filters;
        }
      })
      .addCase(fetchLeads.fulfilled, (state, action) => {
        listFulfilled(state.list, action);
      })
      .addCase(fetchLeads.rejected, (state, action) => {
        listRejected(state.list, action);
      })

      .addCase(respondToLead.pending, state => {
        state.respondStatus = 'loading';
        state.respondError = null;
      })
      .addCase(respondToLead.fulfilled, (state, action) => {
        state.respondStatus = 'succeeded';
        // Patch the row in place so the list reflects the decision without a refetch.
        const index = state.list.items.findIndex(lead => lead.id === action.payload.id);
        if (index !== -1) {
          state.list.items[index] = {...state.list.items[index], ...action.payload};
        }
      })
      .addCase(respondToLead.rejected, (state, action) => {
        state.respondStatus = 'failed';
        state.respondError = action.payload?.message ?? 'Could not send your response.';
      });
  },
});

export const {setFilters, clearRespondState} = leadsSlice.actions;

export const selectLeads = state => state.leads.list.items;
export const selectLeadsStatus = state => state.leads.list.status;
export const selectLeadsError = state => state.leads.list.error;
export const selectCanLoadMoreLeads = state => canLoadMore(state.leads.list);

/** Leads whose contact details have unlocked — the ones a developer can act on. */
export const selectUnlockedLeads = state =>
  state.leads.list.items.filter(lead => lead.contact_unlocked);

export default leadsSlice.reducer;
