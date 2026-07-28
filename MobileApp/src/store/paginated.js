/**
 * Shared shape + reducer handlers for every server-paginated list.
 *
 * Page 1 replaces the list (a fresh search or pull-to-refresh); any later page
 * appends. Keeping this in one place stops each screen from inventing its own
 * "am I loading more or reloading?" logic.
 */

export const initialListState = () => ({
  items: [],
  page: 0, // last page successfully loaded; 0 = nothing yet
  lastPage: 1,
  total: 0,
  perPage: 20,
  status: 'idle', // idle | loading | loadingMore | succeeded | failed
  error: null,
  /** The search/filter params the current list was loaded with. */
  params: {},
});

/** True when another page exists and we are not already fetching it. */
export const canLoadMore = list =>
  list.page > 0 && list.page < list.lastPage && list.status !== 'loading' && list.status !== 'loadingMore';

export function listPending(list, action) {
  const page = action.meta.arg?.page ?? 1;
  list.status = page > 1 ? 'loadingMore' : 'loading';
  list.error = null;
}

export function listFulfilled(list, action) {
  const {data, meta} = action.payload;
  const page = meta?.current_page ?? 1;

  // Append on later pages; replace on page 1 so a new search never shows stale rows.
  if (page > 1) {
    // Guard against a duplicated page (double-tap on "load more") creating repeats.
    const seen = new Set(list.items.map(item => item.id));
    list.items = list.items.concat(data.filter(item => !seen.has(item.id)));
  } else {
    list.items = data;
  }

  list.page = page;
  list.lastPage = meta?.last_page ?? 1;
  list.total = meta?.total ?? data.length;
  list.perPage = meta?.per_page ?? list.perPage;
  list.status = 'succeeded';
  list.error = null;
}

export function listRejected(list, action) {
  list.status = 'failed';
  list.error = action.payload?.message ?? 'Could not load the list.';
}
