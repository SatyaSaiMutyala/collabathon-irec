import {useCallback, useRef} from 'react';
import {useFocusEffect} from '@react-navigation/native';

/**
 * Re-runs a screen's loader every time it regains focus, skipping the first.
 *
 * Tab screens stay mounted, so a mount-only fetch is only ever correct until something
 * changes the data from somewhere else. On the developer side that happens constantly:
 * accepting a request on the Requests tab moves a broker into Partners, changes the
 * counts on the Dashboard and flips a listing's queue on My Listings — none of which
 * those screens would notice, leaving pull-to-refresh as the only way to correct them.
 *
 * The first focus is skipped deliberately. Every screen using this already issues its
 * opening fetch from its own mount effect (in some cases a debounced one that must keep
 * owning the query), and firing here as well would send the same request twice on open.
 *
 * The loader is held in a ref rather than listed as a dependency: on screens whose
 * loader closes over search and filter state, a dependency would re-run this on every
 * keystroke while the screen is focused, which is the debounce's job and not this one's.
 *
 * `onLeave` runs on the way out. Screens whose list can legitimately come back empty
 * pass a list-invalidating action here, so the next visit opens on a skeleton rather
 * than on last visit's "nothing here" panel — see `listInvalidated` in store/paginated.
 */
export function useRefreshOnFocus(refresh, onLeave) {
  const refreshRef = useRef(refresh);
  refreshRef.current = refresh;

  const onLeaveRef = useRef(onLeave);
  onLeaveRef.current = onLeave;

  const isFirstFocus = useRef(true);

  useFocusEffect(
    useCallback(() => {
      if (isFirstFocus.current) {
        isFirstFocus.current = false;
      } else {
        refreshRef.current();
      }

      return () => onLeaveRef.current?.();
    }, []),
  );
}

export default useRefreshOnFocus;
