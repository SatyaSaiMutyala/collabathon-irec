import client from './client';

/**
 * Thin transport layer — one function per endpoint, no state.
 * Every list call forwards pagination/search/filter params to the server;
 * nothing is filtered client-side.
 */

/**
 * Flattens a payload into FormData for the one endpoint that carries a file.
 *
 * Multipart has no types — everything arrives as a string — so booleans go as 1/0 for
 * Laravel's `boolean` rule, arrays go as `key[]` repeats so they arrive as arrays, and
 * nulls are dropped rather than sent as the literal "null" that a `nullable` rule would
 * happily store. The file part is the {uri, name, type} object React Native's fetch
 * understands.
 */
function toFormData(payload) {
  const form = new FormData();

  Object.entries(payload).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') {
      return;
    }

    if (key === 'photo') {
      form.append('photo', value);
      return;
    }

    if (Array.isArray(value)) {
      value.forEach(entry => form.append(`${key}[]`, String(entry)));
      return;
    }

    form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
  });

  return form;
}

// ---------------------------------------------------------------- auth
export const authApi = {
  /**
   * Registration carries an optional passport photo, so it goes out as multipart when
   * one was picked and as plain JSON when it was not — a JSON body cannot carry a file,
   * and forcing multipart on every registration would turn every boolean and array into
   * a string the server then has to un-stringify.
   */
  register: payload =>
    payload.photo
      ? client.post('/auth/register', toFormData(payload), {
          headers: {'Content-Type': 'multipart/form-data'},
        })
      : client.post('/auth/register', payload),
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

/**
 * The developer's own inventory — every project assigned to them, including ones
 * still awaiting their decision. Distinct from `developersApi.properties`, which is
 * the public view and only ever returns accepted + active listings.
 */
export const myPropertiesApi = {
  /** @param {{page?:number, per_page?:number, search?:string, developer_status?:string,
   *           status?:string, type?:string, city?:string, project_status?:string,
   *           sort?:string, direction?:string}} params */
  list: params => client.get('/my/properties', {params}),
  show: id => client.get(`/my/properties/${id}`),
  summary: () => client.get('/my/properties/summary'),
  /** Only the cities/types/stages present in this developer's own inventory. */
  filterOptions: () => client.get('/my/properties/filters'),
  /** Accepting is what makes the project visible to channel partners. */
  respond: (id, payload) => client.patch(`/my/properties/${id}/response`, payload),
};

export const leadsApi = {
  list: params => client.get('/leads', {params}),
  respond: (id, payload) => client.patch(`/leads/${id}`, payload),
};

// ---------------------------------------------------------------- push
export const pushApi = {
  registerDevice: payload => client.post('/auth/device-token', payload),
  /** Body on a DELETE — axios needs it under `data`, not as the second argument. */
  forgetDevice: payload => client.delete('/auth/device-token', {data: payload}),
};

// ---------------------------------------------------------------- channel partners
export const partnersApi = {
  /** @param {{page?:number, per_page?:number, search?:string, city?:string, state?:string,
   *           segment?:string, from?:string, to?:string, sort?:string, direction?:string}} params */
  list: params => client.get('/partners', {params}),
  show: brokerId => client.get(`/partners/${brokerId}`),
  /** The listings this partner was accepted on — paginated, a partner can have dozens. */
  projects: (brokerId, params) => client.get(`/partners/${brokerId}/projects`, {params}),
  /** Only the cities/states/segments present in this developer's own roster. */
  filterOptions: () => client.get('/partners/filters'),
};
