import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  BackHeader,
  AppText,
  Button,
  Chip,
  Input,
  PaginatedList,
  PartnerCard,
  PartnerCardSkeleton,
  RightDrawer,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchNextPartners,
  fetchPartnerFilters,
  fetchPartners,
  selectPartnerOptions,
  selectPartnersList,
} from '../../store/slices/partnersSlice';

/**
 * Partners — the brokers this developer has accepted.
 *
 * Search and filters are request parameters, not a pass over `items`. The roster runs to
 * thousands of rows and only one page is ever in memory, so filtering what happens to be
 * loaded would answer a different question than the one asked.
 *
 * The filters live in a drawer rather than inline: there are three of them plus a sort,
 * and above the list they cost more vertical space than the first two rows of results.
 * Draft state inside the drawer is separate from the applied state, so opening it and
 * backing out does not refetch.
 */
const SORTS = [
  {key: 'last_collaborated_at', label: 'Recently connected', direction: 'desc'},
  {key: 'projects_count', label: 'Most projects', direction: 'desc'},
  {key: 'name', label: 'A–Z', direction: 'asc'},
];

/** Long enough that typing a name is one request, short enough to feel immediate. */
const SEARCH_DEBOUNCE_MS = 350;

const EMPTY_FILTERS = {city: null, segment: null, sort: SORTS[0]};

const PartnersScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const list = useAppSelector(selectPartnersList);
  const options = useAppSelector(selectPartnerOptions);

  const [search, setSearch] = useState('');
  const [applied, setApplied] = useState(EMPTY_FILTERS);
  const [draft, setDraft] = useState(EMPTY_FILTERS);
  const [drawerOpen, setDrawerOpen] = useState(false);

  // What the server is actually asked for. Blank values are dropped rather than sent as
  // empty strings, which the API would otherwise have to treat as "no filter" itself.
  const params = useMemo(
    () => ({
      ...(search.trim() ? {search: search.trim()} : {}),
      ...(applied.city ? {city: applied.city} : {}),
      ...(applied.segment ? {segment: applied.segment} : {}),
      sort: applied.sort.key,
      direction: applied.sort.direction,
    }),
    [search, applied],
  );

  useEffect(() => {
    dispatch(fetchPartnerFilters());
  }, [dispatch]);

  // One debounce over the whole param set, so applying a filter mid-search does not fire
  // a second request for the intermediate state.
  useEffect(() => {
    const timer = setTimeout(() => {
      dispatch(fetchPartners({page: 1, ...params}));
    }, SEARCH_DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [dispatch, params]);

  const reload = useCallback(() => {
    dispatch(fetchPartners({page: 1, ...params}));
  }, [dispatch, params]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextPartners());
  }, [dispatch]);

  // Sort is always set, so it does not count as a filter the user has to clear.
  const activeCount = [applied.city, applied.segment].filter(Boolean).length;

  const openDrawer = () => {
    setDraft(applied);
    setDrawerOpen(true);
  };

  const apply = () => {
    setApplied(draft);
    setDrawerOpen(false);
  };

  const clearDraft = () => setDraft(EMPTY_FILTERS);

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
        title="Partners"
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
        placeholder="Search name, company or RERA"
        leftIcon="search-outline"
        autoCapitalize="none"
        autoCorrect={false}
        value={search}
        onChangeText={setSearch}
      />

      <View style={styles.summary(spacing)}>
        <AppText variant="caption" color={colors.textMuted}>
          {list.total} {list.total === 1 ? 'Channel Partner' : 'Channel Partners'} · {applied.sort.label}
        </AppText>
      </View>

      <PaginatedList
        list={list}
        onRefresh={reload}
        onEndReached={handleEndReached}
        renderSkeleton={() => <PartnerCardSkeleton />}
        emptyIcon="people-outline"
        emptyFiltered={activeCount || search.trim()}
        emptyTitle={activeCount || search.trim() ? 'No partners match' : 'No partners yet'}
        emptyMessage={
          activeCount || search.trim()
            ? 'Try a different search or clear the filters.'
            : 'Channel partners appear here once you accept their request.'
        }
        renderItem={({item}) => (
          <PartnerCard
            partner={item}
            onPress={() => navigation.navigate('BrokerDetail', {partnerId: item.id})}
          />
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
          {drawerSection('City', options.cities ?? [], draft.city, city =>
            setDraft(current => ({...current, city})),
          )}
          {drawerSection('Segment', options.segments ?? [], draft.segment, segment =>
            setDraft(current => ({...current, segment})),
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

export default PartnersScreen;
