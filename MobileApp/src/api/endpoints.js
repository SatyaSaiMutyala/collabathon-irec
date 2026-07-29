import client from './client';

/**
 * Thin transport layer — one function per endpoint, no state.
 * Every list call forwards pagination/search/filter params to the server;
 * nothing is filtered client-side.
 */

// ---------------------------------------------------------------- auth
export const authApi = {
  register: payload => client.post('/auth/register', payload),
  login: payload => client.post('/auth/login', payload),
  me: () => client.get('/auth/me'),
  logout: () => client.post('/auth/logout'),
};

export const dashboardApi = {
  /** Developer headline figures + trend series. */
  fetch: () => client.get('/dashboard'),
};

// ---------------------------------------------------------------- catalogue
export const propertiesApi = {
  /** @param {{page?:number, per_page?:number, search?:string, sort?:string, direction?:string,
   *           developer_id?:number, type?:string, city?:string, price_min?:number, price_max?:number}} params */
  list: params => client.get('/properties', {params}),
  show: id => client.get(`/properties/${id}`),
  recordView: id => client.post(`/properties/${id}/view`),
  markInterested: id => client.post(`/properties/${id}/interest`),
};

export const developersApi = {
  list: params => client.get('/developers', {params}),
  show: id => client.get(`/developers/${id}`),
  properties: (id, params) => client.get(`/developers/${id}/properties`, {params}),
};

export const leadsApi = {
  list: params => client.get('/leads', {params}),
  respond: (id, payload) => client.patch(`/leads/${id}`, payload),
};
