import React from 'react';
import {ActivityIndicator, FlatList, RefreshControl, View} from 'react-native';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import EmptyState from './EmptyState';

/**
 * FlatList wired to the store's paginated list shape.
 *
 * Handles the four states a server-paginated list actually has — first load,
 * empty, error, and loading-more — so no screen reimplements them.
 */
const PaginatedList = React.forwardRef(({
  list, // {items, status, error, page, lastPage, total}
  renderItem,
  keyExtractor = item => String(item.id),
  onRefresh,
  onEndReached,
  emptyTitle = 'Nothing here yet',
  emptyMessage = 'Try a different search or filter.',
  /** Ionicons name for the empty state — pick one that matches what the list holds. */
  emptyIcon,
  /** True when the list is empty because of a search or filter, not because there is no data. */
  emptyFiltered = false,
  /** Skeleton row for the first load. Pass the one shaped like this list's own card. */
  renderSkeleton,
  skeletonCount = 5,
  ListHeaderComponent,
  contentContainerStyle,
  ...rest
}, ref) => {
  const {colors, spacing} = useAppTheme();

  // `idle` counts as first load, not as empty. A screen renders once before its
  // useEffect dispatches anything, and treating that frame as "no results" is what
  // made every list flash its empty state before the data arrived.
  const isFirstLoad =
    (list.status === 'loading' || list.status === 'idle') && list.items.length === 0;
  // The first load renders placeholder rows *through the same FlatList* rather than
  // returning a different tree. Swapping a plain View for a FlatList when the data
  // lands unmounts the scroller and mounts a fresh one, and the height change between
  // a fixed stack of skeletons and the real page is what made the screen lurch on
  // first open. One list, one mount, one scroll position.
  const skeletonRows = React.useMemo(
    () => Array.from({length: skeletonCount}, (_, index) => ({__skeleton: true, id: `skeleton-${index}`})),
    [skeletonCount],
  );

  const isLoadingMore = list.status === 'loadingMore';
  const hasFailed = list.status === 'failed' && list.items.length === 0;

  if (hasFailed) {
    return (
      <View style={{flex: 1, justifyContent: 'center'}}>
        <EmptyState
          icon="cloud-offline-outline"
          title="Connection problem"
          message={list.error}
          actionLabel={onRefresh ? 'Try again' : undefined}
          onAction={onRefresh}
          style={{marginTop: 0}}
        />
      </View>
    );
  }

  const showSkeleton = isFirstLoad && !!renderSkeleton;

  if (isFirstLoad && !renderSkeleton) {
    return (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <FlatList
      // Forwarded so a screen can send the list back to the top when the query changes.
      // Re-filtering keeps the FlatList's offset, which leaves the user looking at the
      // middle of a result set they have not seen the start of.
      ref={ref}
      data={showSkeleton ? skeletonRows : list.items}
      keyExtractor={showSkeleton ? item => item.id : keyExtractor}
      renderItem={showSkeleton ? ({index}) => renderSkeleton(index) : renderItem}
      showsVerticalScrollIndicator={false}
      ListHeaderComponent={ListHeaderComponent}
      // flexGrow (not just paddingBottom) only while genuinely empty — that's what
      // lets EmptyState centre itself in the space below the header instead of
      // sitting docked right under it. Applying this unconditionally would also
      // centre a short *real* list vertically instead of anchoring it to the top,
      // which is the wrong look once there's actual content to scroll.
      contentContainerStyle={[
        {paddingBottom: spacing.xxl},
        list.items.length === 0 && !showSkeleton && {flexGrow: 1, justifyContent: 'center'},
        contentContainerStyle,
      ]}
      // Refreshing is only true on an explicit page-1 reload, never on load-more,
      // otherwise the spinner flashes every time the user reaches the bottom.
      refreshControl={
        onRefresh ? (
          <RefreshControl
            refreshing={list.status === 'loading' && list.items.length > 0}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        ) : undefined
      }
      // Placeholder rows must not trigger paging — the skeleton stack is taller than the
      // viewport, so onEndReached would fire against a list that has not loaded page 1.
      onEndReached={showSkeleton ? undefined : onEndReached}
      onEndReachedThreshold={0.4}
      ListFooterComponent={
        isLoadingMore ? (
          <View style={{paddingVertical: spacing.md, alignItems: 'center'}}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : list.items.length > 0 && list.page >= list.lastPage ? (
          <AppText
            variant="caption"
            color={colors.textMuted}
            style={{textAlign: 'center', paddingVertical: spacing.md}}>
            {list.total} {list.total === 1 ? 'result' : 'results'}
          </AppText>
        ) : null
      }
      ListEmptyComponent={
        <EmptyState
          icon={emptyIcon}
          title={emptyTitle}
          message={emptyMessage}
          filtered={emptyFiltered}
          style={{marginTop: 0}}
        />
      }
      {...rest}
    />
  );
});

PaginatedList.displayName = 'PaginatedList';

export default PaginatedList;
