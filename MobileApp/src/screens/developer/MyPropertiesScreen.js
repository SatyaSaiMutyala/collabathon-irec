import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  BackHeader,
  Badge,
  Button,
  Chip,
  Input,
  MyProjectCardSkeleton,
  PaginatedList,
  PropertyCard,
  RightDrawer,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {useRefreshOnFocus} from '../../hooks/useRefreshOnFocus';
import {
  fetchMyProperties,
  fetchMyPropertiesSummary,
  fetchMyPropertyFilters,
  fetchNextMyProperties,
  invalidateMyProperties,
  selectMyProperties,
  selectMyPropertiesSummary,
  selectMyPropertyOptions,
} from '../../store/slices/myPropertiesSlice';

/**
 * The developer's inventory. Unlike the public developer profile, this includes projects
 * the admin has assigned but the developer has not yet accepted — those are the ones
 * that need action, so they lead.
 *
 * Search and filters are request parameters, not a pass over `items`: only one page is
 * ever in memory, so filtering what happens to be loaded would answer a different
 * question than the one asked. Draft state inside the drawer is separate from the
 * applied state, so opening it and backing out does not refetch.
 */
const STATUSES = [
  {key: 'pending', label: 'Awaiting you'},
  {key: 'accepted', label: 'Accepted'},
  {key: 'declined', label: 'Declined'},
  {key: '', label: 'All'},
];

const SORTS = [
  {key: 'created_at', label: 'Newest', direction: 'desc'},
  {key: 'interests', label: 'Most requested', direction: 'desc'},
  {key: 'price', label: 'Price', direction: 'desc'},
  {key: 'name', label: 'A–Z', direction: 'asc'},
];

/** Long enough that typing a project name is one request, short enough to feel immediate. */
const SEARCH_DEBOUNCE_MS = 350;

const STATUS_TONE = {pending: 'warning', accepted: 'success', declined: 'danger'};
const STATUS_LABEL = {
  pending: 'Awaiting your response',
  accepted: 'Accepted',
  declined: 'Declined',
};

// Land on the queue that needs work. The effect below moves to "All" when nothing is
// pending, so the screen is never an empty tab on first open.
const DEFAULT_FILTERS = {
  status: 'pending',
  city: null,
  type: null,
  stage: null,
  sort: SORTS[0],
};

const MyPropertiesScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const list = useAppSelector(selectMyProperties);
  const summary = useAppSelector(selectMyPropertiesSummary);
  const options = useAppSelector(selectMyPropertyOptions);

  const [search, setSearch] = useState('');
  const [applied, setApplied] = useState(DEFAULT_FILTERS);
  const [draft, setDraft] = useState(DEFAULT_FILTERS);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [hasAutoSwitched, setHasAutoSwitched] = useState(false);

  const listRef = useRef(null);
  const isFirstQuery = useRef(true);

  // What the server is actually asked for. Blank values are dropped rather than sent as
  // empty strings, which the API would otherwise have to treat as "no filter" itself.
  const params = useMemo(
    () => ({
      ...(search.trim() ? {search: search.trim()} : {}),
      ...(applied.status ? {developer_status: applied.status} : {}),
      ...(applied.city ? {city: applied.city} : {}),
      ...(applied.type ? {type: applied.type} : {}),
      ...(applied.stage ? {project_status: applied.stage} : {}),
      sort: applied.sort.key,
      direction: applied.sort.direction,
    }),
    [search, applied],
  );

  useEffect(() => {
    dispatch(fetchMyPropertyFilters());
  }, [dispatch]);

  // The counts describe the whole inventory, so they do not move when a filter changes.
  // Fetching on null covers both mount and the slice clearing it after accept/decline,
  // without putting an aggregate query behind every keystroke.
  useEffect(() => {
    if (summary === null) {
      dispatch(fetchMyPropertiesSummary());
    }
  }, [dispatch, summary]);

  // One debounce over the whole param set, so applying a filter mid-search does not fire
  // a second request for the intermediate state. The first run is NOT debounced: there is
  // no keystroke to wait for on mount, and holding the skeleton an extra 350ms is the
  // difference between the screen appearing settled and appearing to load twice.
  useEffect(() => {
    const timer = setTimeout(
      () => {
        // Cleared here rather than in the effect body: an effect that is torn down and
        // re-run before its timer fires (StrictMode's double-invoke) must still count as
        // the first query, or the mount pays the debounce after all.
        isFirstQuery.current = false;

        // Back to the top before the new page lands. A FlatList keeps its offset across
        // a data change, so re-filtering while scrolled down leaves the user halfway into
        // a result set they have not seen the start of.
        listRef.current?.scrollToOffset({offset: 0, animated: false});
        dispatch(fetchMyProperties({page: 1, ...params}));
      },
      isFirstQuery.current ? 0 : SEARCH_DEBOUNCE_MS,
    );

    return () => clearTimeout(timer);
  }, [dispatch, params]);

  useEffect(() => {
    if (!hasAutoSwitched && summary && summary.pending === 0 && applied.status === 'pending') {
      setHasAutoSwitched(true);
      setApplied(current => ({...current, status: ''}));
    }
  }, [summary, applied.status, hasAutoSwitched]);

  // Pull-to-refresh is the one place the counts are worth re-asking for: the admin may
  // have assigned something since the screen mounted.
  const reload = useCallback(() => {
    dispatch(fetchMyProperties({page: 1, ...params}));
    dispatch(fetchMyPropertiesSummary());
  }, [dispatch, params]);

  // Also on every return to the tab, not just on a pull. Accepting a request moves that
  // listing out of the "Awaiting you" queue, so coming back from the Requests tab to a
  // board that still shows it there — and a pending count that still includes it — was
  // the stalest screen in the app. Re-uses the same loader as pull-to-refresh, so the
  // current search and filters are preserved rather than reset.
  const invalidate = useCallback(() => dispatch(invalidateMyProperties()), [dispatch]);

  useRefreshOnFocus(reload, invalidate);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextMyProperties());
  }, [dispatch]);

  // Sort is always set and the status queue defaults to "Awaiting you", so neither
  // counts as a filter the user has to clear — only departures from the default do.
  const activeCount = [
    applied.status !== DEFAULT_FILTERS.status ? applied.status || 'all' : null,
    applied.city,
    applied.type,
    applied.stage,
  ].filter(Boolean).length;

  const hasQuery = activeCount > 0 || !!search.trim();

  const openDrawer = () => {
    setDraft(applied);
    setDrawerOpen(true);
  };

  const apply = () => {
    setApplied(draft);
    setDrawerOpen(false);
  };

  // Clearing goes to "All", not back to the pending queue: a user who opened the drawer
  // to widen the view should not be handed a narrower one.
  const clearDraft = () => setDraft({...DEFAULT_FILTERS, status: ''});

  const countFor = key => {
    if (!summary) {
      return null;
    }
    return key === '' ? summary.total : summary[key];
  };

  const statusLabel = STATUSES.find(s => s.key === applied.status)?.label ?? 'All';

  const drawerSection = (label, values, selected, onSelect) =>
    values.length > 0 && (
      <View style={{marginBottom: spacing.lg}}>
        <AppText variant="overline" color={colors.textMuted} style={{marginBottom: spacing.xs}}>
          {label}
        </AppText>
        <View style={styles.chipWrap}>
          {values.map(value => (
            <View key={value} style={styles.chipGap}>
              <Chip
                label={value}
                compact
                active={selected === value}
                // Tapping the active chip clears it — no separate "All" chip to maintain.
                onPress={() => onSelect(selected === value ? null : value)}
              />
            </View>
          ))}
        </View>
      </View>
    );

  return (
    <ScreenContainer edges={['top']}>
      <BackHeader
        navigation={navigation}
        title="My Listings"
        fallbackRoute="DashboardTab"
        right={
          <TouchableOpacity onPress={openDrawer} hitSlop={10} style={styles.filterButton}>
            <Icon name="options-outline" size={moderateScale(20)} color={colors.textPrimary} />
            {activeCount > 0 && (
              <View style={[styles.badge, {backgroundColor: colors.primary}]}>
                <AppText variant="caption" color={colors.textInverse} style={styles.badgeText}>
                  {activeCount}
                </AppText>
              </View>
            )}
          </TouchableOpacity>
        }
      />

      <Input
        placeholder="Search listing, locality or city"
        leftIcon="search-outline"
        autoCapitalize="none"
        autoCorrect={false}
        value={search}
        onChangeText={setSearch}
      />

      <View style={styles.summary(spacing)}>
        <AppText variant="caption" color={colors.textMuted}>
          {list.total} {list.total === 1 ? 'Listing' : 'Listings'} · {statusLabel}
          {summary ? ` · ${summary.live} Live to Channel Partners` : ''}
        </AppText>
      </View>

      <PaginatedList
        ref={listRef}
        list={list}
        onRefresh={reload}
        onEndReached={handleEndReached}
        // Without this, PaginatedList falls back to a centred spinner in a plain View and
        // swaps it for a FlatList when the page lands — a fresh scroller at a different
        // height, which is what made the screen jump on open.
        renderSkeleton={() => <MyProjectCardSkeleton />}
        emptyIcon="business-outline"
        emptyFiltered={hasQuery}
        emptyTitle={
          hasQuery
            ? 'No listings match'
            : applied.status === 'pending'
              ? 'Nothing awaiting you'
              : 'No listings here'
        }
        emptyMessage={
          hasQuery
            ? 'Try a different search or clear the filters.'
            : 'Listings our team assigns to you will appear here for review.'
        }
        renderItem={({item}) => (
          <View>
            <PropertyCard
              project={item}
              onPress={() => navigation.navigate('PropertyLeads', {projectId: item.id})}
            />

            {/* The gate, stated on the card: channel partners can only reach a listing
                once the developer has accepted AND our team has it active. */}
            <View style={styles.cardMeta(spacing)}>
              {/* "Live" is the primary state label per the language pass, but it is only
                  true once the listing is actually published — an accepted listing still
                  waiting on us is not live, so the badge reads Accepted until it is. */}
              <Badge
                label={
                  item.isLive
                    ? 'Live'
                    : (STATUS_LABEL[item.developerStatus] ?? item.developerStatus)
                }
                tone={STATUS_TONE[item.developerStatus] ?? 'muted'}
              />
              {item.isLive && (
                <AppText variant="caption" color={colors.success} style={{marginLeft: spacing.xs}}>
                  Live to Channel Partners
                </AppText>
              )}
              {item.developerStatus === 'accepted' && !item.isLive && (
                <AppText
                  variant="caption"
                  color={colors.textMuted}
                  style={{marginLeft: spacing.xs}}>
                  Not published yet
                </AppText>
              )}
              {item.interestsCount > 0 && (
                <AppText
                  variant="caption"
                  color={colors.primaryDark}
                  style={{marginLeft: spacing.xs}}>
                  {item.interestsCount} requested
                </AppText>
              )}
            </View>
          </View>
        )}
      />

      <RightDrawer visible={drawerOpen} onClose={() => setDrawerOpen(false)}>
        <View style={styles.drawerHead(spacing)}>
          <AppText variant="h3">Filters</AppText>
          <TouchableOpacity onPress={() => setDrawerOpen(false)} hitSlop={10}>
            <Icon name="close" size={moderateScale(22)} color={colors.textPrimary} />
          </TouchableOpacity>
        </View>

        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{paddingHorizontal: spacing.lg, paddingBottom: spacing.lg}}>
          {/* Status keeps its counts — it is the queue the developer works from, so
              knowing how many are waiting matters before choosing, not after. */}
          <View style={{marginBottom: spacing.lg}}>
            <AppText variant="overline" color={colors.textMuted} style={{marginBottom: spacing.xs}}>
              STATUS
            </AppText>
            <View style={styles.chipWrap}>
              {STATUSES.map(option => {
                const count = countFor(option.key);
                return (
                  <View key={option.key || 'all'} style={styles.chipGap}>
                    <Chip
                      compact
                      label={count === null ? option.label : `${option.label} (${count})`}
                      active={draft.status === option.key}
                      onPress={() => setDraft(current => ({...current, status: option.key}))}
                    />
                  </View>
                );
              })}
            </View>
          </View>

          {drawerSection('CITY', options.cities ?? [], draft.city, city =>
            setDraft(current => ({...current, city})),
          )}
          {drawerSection('TYPE', options.types ?? [], draft.type, type =>
            setDraft(current => ({...current, type})),
          )}
          {drawerSection('STAGE', options.stages ?? [], draft.stage, stage =>
            setDraft(current => ({...current, stage})),
          )}

          <View>
            <AppText variant="overline" color={colors.textMuted} style={{marginBottom: spacing.xs}}>
              SORT
            </AppText>
            <View style={styles.chipWrap}>
              {SORTS.map(option => (
                <View key={option.key} style={styles.chipGap}>
                  <Chip
                    label={option.label}
                    compact
                    active={draft.sort.key === option.key}
                    onPress={() => setDraft(current => ({...current, sort: option}))}
                  />
                </View>
              ))}
            </View>
          </View>
        </ScrollView>

        <View style={styles.drawerFoot(spacing, colors)}>
          <View style={{flex: 1, marginRight: spacing.xs}}>
            <Button label="Clear" variant="outline" size="md" onPress={clearDraft} />
          </View>
          <View style={{flex: 1, marginLeft: spacing.xs}}>
            <Button label="Apply" size="md" onPress={apply} />
          </View>
        </View>
      </RightDrawer>
    </ScreenContainer>
  );
};

const styles = {
  heading: spacing => ({
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: spacing.sm,
    marginBottom: spacing.md,
  }),
  summary: spacing => ({
    marginBottom: spacing.xs,
  }),
  cardMeta: spacing => ({
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    marginTop: -spacing.xs,
    marginBottom: spacing.sm,
  }),
  filterButton: {
    padding: moderateScale(4),
  },
  badge: {
    position: 'absolute',
    top: -moderateScale(2),
    right: -moderateScale(4),
    minWidth: moderateScale(16),
    height: moderateScale(16),
    borderRadius: moderateScale(999),
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: moderateScale(3),
  },
  badgeText: {
    fontSize: moderateScale(9.5),
    lineHeight: moderateScale(12),
  },
  chipWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  chipGap: {
    marginRight: moderateScale(6),
    marginBottom: moderateScale(6),
  },
  drawerHead: spacing => ({
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.sm,
  }),
  drawerFoot: (spacing, colors) => ({
    flexDirection: 'row',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.sm,
    paddingBottom: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  }),
};

export default MyPropertiesScreen;
