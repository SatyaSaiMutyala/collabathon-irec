import {createSlice} from '@reduxjs/toolkit';

const initialState = {
  byProjectId: {},
};

const leadsSlice = createSlice({
  name: 'leads',
  initialState,
  reducers: {
    markInterested: (state, action) => {
      const {projectId, developerId} = action.payload;
      state.byProjectId[projectId] = {
        projectId,
        developerId,
        status: 'pending',
        markedAt: new Date().toISOString(),
      };
    },
    /** Simulated developer response, until backend/notifications drive this. */
    setLeadStatus: (state, action) => {
      const lead = state.byProjectId[action.payload.projectId];
      if (lead) {
        lead.status = action.payload.status;
      }
    },
  },
});

export const {markInterested, setLeadStatus} = leadsSlice.actions;
export default leadsSlice.reducer;
