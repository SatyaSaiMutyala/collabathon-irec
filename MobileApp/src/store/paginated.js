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
  status: 'idle', // idle | loading | refreshing | loadingMore | succeeded | failed
  error: null,
  /** The search/filter params the current list was loaded with. */
  params: {},
});

/** True when another page exists and we are not already fetching it. */
export const canLoadMore = list =>
  list.page > 0 &&
  list.page < list.lastPage &&
  list.status !== 'loading' &&
  list.status !== 'refreshing' &&
  list.status !== 'loadingMore';

/**
 * `refreshing` vs `loading` is the difference between a reload the user should see and
 * one they should not.
 *
 * Every tab screen refetches page 1 on focus, which is what keeps the list honest after
 * a request is made or accepted somewhere else. But a reload of a list that already has
 * rows must not disturb them: with a single `loading` state, switching tabs drove
 * PaginatedList's RefreshControl to `refreshing={true}`, so the pull-to-refresh spinner
 * dropped in and retracted on every tab tap — the list visibly jumping each time even
 * though the rows never changed.
 *
 * So a page-1 fetch over an already-populated list is `refreshing`: the rows stay
 * exactly where they are, no skeleton, no spinner, and the new data swaps in when it
 * arrives. `loading` is reserved for a genuinely empty list, which is the only case
 * where there is nothing to look at and a placeholder is the honest thing to show.
 */
export function listPending(list, action) {
  const page = action.meta.arg?.page ?? 1;

  if (page > 1) {
    list.status = 'loadingMore';
  } else {
    list.status = list.items.length > 0 ? 'refreshing' : 'loading';
  }

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

/**
 * Mark a settled list as stale, without dropping what it holds.
 *
 * Tab screens stay mounted, so whatever a screen painted last is still on it when the
 * user comes back. For a list that came back empty that means last visit's verdict —
 * "No requests yet" — is what greets them, before the refetch has even been dispatched:
 * the answer arrives before the question, and then flickers as the real result lands.
 *
 * Dropping back to `idle` on the way out makes PaginatedList treat the next visit as a
 * first load, so an empty list shows its skeleton until the fresh answer arrives. A list
 * that still holds rows is untouched by this — `idle` only reads as a first load while
 * `items` is empty, so populated lists keep showing their rows exactly as before.
 */
export function listInvalidated(list) {
  if (list.status === 'succeeded' || list.status === 'failed') {
    list.status = 'idle';
  }
}
