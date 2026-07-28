import {useEffect, useState} from 'react';

/**
 * Delays a fast-changing value (a search box) so we issue one request per pause
 * instead of one per keystroke. At 8k concurrent users, un-debounced search is
 * the difference between one query and thirty per user per search.
 */
export function useDebouncedValue(value, delay = 400) {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);

  return debounced;
}

export default useDebouncedValue;
