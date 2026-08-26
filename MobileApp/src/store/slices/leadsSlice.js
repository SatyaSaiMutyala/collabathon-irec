import {createAsyncThunk, createSelector, createSlice} from '@reduxjs/toolkit';
import {leadsApi} from '../../api/endpoints';
import {extractError} from '../../api/client';
import {
  canLoadMore,
  initialListState,
  listFulfilled,
  listInvalidated,
  listPending,
  listRejected,
} from '../paginated';

/**
 * One slice for both roles — /api/v1/leads is scoped server-side by the token, so a
 * broker gets their own leads and a developer gets their inbox from the same call.
 *
 * A broker's phone and email arrive starred until the developer accepts the request —
 * `contact_visible` says which it is. The client never has to hide anything, because
 * the real values were never sent; accepting refetches them with the PATCH response.
 */

const initialState = {
  /** Whatever the current screen asked for — the developer inbox, the broker's requests. */
  list: initialListState(),
  /**
   * The broker's accepted leads, kept apart from `list`.
   *
   * Requests and Partners are two tabs of the same navigator and both stay mounted, so
   * sharing one list would mean whichever was focused last decided what the other one
   * showed. Two lists, two statuses, no cross-talk.
   */
  accepted: initialListState(),
  /**
   * One listing's brokers, for PropertyLeadsScreen.
   *
   * It used to refill `list`, which is the developer's inbox — so opening a listing and
   * going back left the inbox showing that listing's brokers. The workaround was to
   * refetch the inbox on every focus, and that threw away the scroll position each time.
   * A separate list fixes the cause: nobody overwrites anyone, and no screen has to
   * reload just because it came back into view.
   */
  propertyLeads: initialListState(),
  /**
   * Every status-changed lead, for the Notifications screen — kept apart from `list`
   * for the same reason as `accepted`: `list` is filtered per screen (e.g. `interested`
   * only), and Notifications needs the unfiltered feed without stomping on whichever
   * filter the inbox/requests screen last set.
   */
  notifications: initialListState(),
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

/** Every broker who touched one listing — viewed and interested alike. */
export const fetchPropertyLeads = createAsyncThunk(
  'leads/fetchForProperty',
  async ({propertyId, page = 1}, {rejectWithValue}) => {
    try {
      const {data} = await leadsApi.list({page, property_id: propertyId});
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextPropertyLeads = createAsyncThunk(
  'leads/fetchNextForProperty',
  async (_, {getState, dispatch}) => {
    const {propertyLeads} = getState().leads;
    if (!canLoadMore(propertyLeads)) {
      return null;
    }
    const propertyId = propertyLeads.params.propertyId;
    return dispatch(fetchPropertyLeads({propertyId, page: propertyLeads.page + 1})).unwrap();
  },
);

/** The broker's accepted leads — the projects a developer took them on. */
export const fetchAcceptedLeads = createAsyncThunk(
  'leads/fetchAccepted',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await leadsApi.list({page, status: 'accepted', ...filters});
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
  },
);

export const fetchNextAcceptedLeads = createAsyncThunk(
  'leads/fetchNextAccepted',
  async (_, {getState, dispatch}) => {
    const {accepted} = getState().leads;
    if (!canLoadMore(accepted)) {
      return null;
    }
    return dispatch(fetchAcceptedLeads({...accepted.params, page: accepted.page + 1})).unwrap();
  },
);

/**
 * The unfiltered lead feed the Notifications screen renders — every status, not just
 * `interested`, since a broker needs to hear about `accepted`/`declined` too and a
 * developer about `viewed`.
 */
export const fetchNotificationLeads = createAsyncThunk(
  'leads/fetchNotifications',
  async ({page = 1, ...filters} = {}, {rejectWithValue}) => {
    try {
      const {data} = await leadsApi.list({page, ...filters});
      return data;
    } catch (error) {
      return rejectWithValue(extractError(error));
    }
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
    /**
     * Dispatched by Requests/Partners as they lose focus. Both tabs stay mounted, so
     * without this the next visit opens on last visit's empty state before the refetch
     * has started — see `listInvalidated`.
     */
    invalidateList: state => {
      listInvalidated(state.list);
    },
    invalidateAccepted: state => {
      listInvalidated(state.accepted);
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

      .addCase(fetchAcceptedLeads.pending, (state, action) => {
        listPending(state.accepted, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.accepted.params = filters;
        }
      })
      .addCase(fetchAcceptedLeads.fulfilled, (state, action) => {
        listFulfilled(state.accepted, action);
      })
      .addCase(fetchAcceptedLeads.rejected, (state, action) => {
        listRejected(state.accepted, action);
      })

      .addCase(fetchPropertyLeads.pending, (state, action) => {
        listPending(state.propertyLeads, action);
        if ((action.meta.arg?.page ?? 1) === 1) {
          state.propertyLeads.params = {propertyId: action.meta.arg?.propertyId};
        }
      })
      .addCase(fetchPropertyLeads.fulfilled, (state, action) => {
        listFulfilled(state.propertyLeads, action);
      })
      .addCase(fetchPropertyLeads.rejected, (state, action) => {
        listRejected(state.propertyLeads, action);
      })

      .addCase(fetchNotificationLeads.pending, (state, action) => {
        listPending(state.notifications, action);
        const {page, ...filters} = action.meta.arg ?? {};
        if ((page ?? 1) === 1) {
          state.notifications.params = filters;
        }
      })
      .addCase(fetchNotificationLeads.fulfilled, (state, action) => {
        listFulfilled(state.notifications, action);
      })
      .addCase(fetchNotificationLeads.rejected, (state, action) => {
        listRejected(state.notifications, action);
      })

      .addCase(respondToLead.pending, state => {
        state.respondStatus = 'loading';
        state.respondError = null;
      })
      .addCase(respondToLead.fulfilled, (state, action) => {
        state.respondStatus = 'succeeded';
        // Patch the row in place so the list reflects the decision without a refetch.
        // Both the inbox and a listing's broker list can raise this, and the same lead
        // may sit in either, so every list is patched rather than guessing which.
        [state.list, state.propertyLeads, state.accepted, state.notifications].forEach(list => {
          const index = list.items.findIndex(lead => lead.id === action.payload.id);
          if (index !== -1) {
            list.items[index] = {...list.items[index], ...action.payload};
          }
        });
      })
      .addCase(respondToLead.rejected, (state, action) => {
        state.respondStatus = 'failed';
        state.respondError = action.payload?.message ?? 'Could not send your response.';
      });
  },
});

export const {setFilters, clearRespondState, invalidateList, invalidateAccepted} =
  leadsSlice.actions;

export const selectLeads = state => state.leads.list.items;
export const selectAcceptedLeads = state => state.leads.accepted;
export const selectPropertyLeads = state => state.leads.propertyLeads;
export const selectLeadsStatus = state => state.leads.list.status;
export const selectLeadsError = state => state.leads.list.error;
export const selectCanLoadMoreLeads = state => canLoadMore(state.leads.list);

/**
 * Leads the broker has actually requested — a bare view is not a request.
 *
 * Memoized: .filter() builds a new array on every call, which reads to React-Redux as a
 * changed result on unchanged state and re-renders whatever subscribes to it. createSelector
 * recomputes only when `items` itself changes.
 */
export const selectUnlockedLeads = createSelector(
  [state => state.leads.list.items],
  items => items.filter(lead => lead.contact_unlocked),
);

/**
 * Backs the broker detail screen. There is no GET /leads/{id}, so the screen reads the
 * row whichever list already loaded and stays live through `respondToLead`, which
 * patches that same row with the unmasked contact the accept response carries back.
 *
 * Checks every bucket, not just `list` — BrokerDetailScreen is opened with a `leadId`
 * from more than one screen (the inbox, a listing's own CP Interests, Notifications),
 * and each of those loads into its own separate bucket (see the docblocks on `list`/
 * `propertyLeads`/`notifications` above) rather than sharing one. Reading only `list`
 * meant a lead opened from PropertyLeadsScreen showed "This request is no longer
 * available" any time it hadn't *also* happened to be loaded into the inbox in this
 * same session — same reasoning `respondToLead.fulfilled` already applies when
 * patching a response back into every list that might be holding this lead.
 */
export const selectLeadById = (state, leadId) => {
  const {list, propertyLeads, accepted, notifications} = state.leads;
  for (const bucket of [list, propertyLeads, accepted, notifications]) {
    const lead = bucket.items.find(item => item.id === leadId);
    if (lead) {
      return lead;
    }
  }
  return undefined;
};

export default leadsSlice.reducer;
