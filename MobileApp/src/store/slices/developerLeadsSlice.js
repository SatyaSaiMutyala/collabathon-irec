import {createSlice} from '@reduxjs/toolkit';

const initialState = {
  /** {[projectId]: {[brokerId]: 'accepted' | 'declined'}} — overrides on top of the seed leads. */
  responses: {},
};

const developerLeadsSlice = createSlice({
  name: 'developerLeads',
  initialState,
  reducers: {
    respondToLead: (state, action) => {
      const {projectId, brokerId, status} = action.payload;
      if (!state.responses[projectId]) {
        state.responses[projectId] = {};
      }
      state.responses[projectId][brokerId] = status;
    },
  },
});

export const {respondToLead} = developerLeadsSlice.actions;
export default developerLeadsSlice.reducer;
