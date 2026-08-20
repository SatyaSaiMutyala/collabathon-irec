import React, {useCallback, useEffect} from 'react';
import {useAppTheme} from '../../theme';
import {
  AppText,
  BrokerLeadCard,
  BrokerLeadCardSkeleton,
  PaginatedList,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchLeads, fetchNextLeads} from '../../store/slices/leadsSlice';

/**
 * The developer's inbox. Only leads that reached "interested" are actionable, so
 * that is what the server is asked for — a bare view is not a request.
 */
const RequestBrokersScreen = ({navigation}) => {
  const {spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const list = useAppSelector(state => state.leads.list);

  const loadFirstPage = useCallback(() => {
    dispatch(fetchLeads({page: 1, status: 'interested'}));
  }, [dispatch]);

  // On focus, not on mount. `leads.list` is shared with PropertyLeads, which refills it
  // scoped to one listing — and tab screens stay mounted, so a mount-only fetch would
  // leave this inbox showing that listing's brokers after coming back from it.
  // On mount, not on focus. Nothing else writes to this list any more, so refetching
  // every time the screen comes back would only throw away the scroll position the user
  // had when they tapped through to a detail screen. Pull-to-refresh is the way back.
  useEffect(() => {
    loadFirstPage();
  }, [loadFirstPage]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextLeads());
  }, [dispatch]);

  return (
    <ScreenContainer edges={['top']}>
      <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
        Requests
      </AppText>

      <PaginatedList
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        renderSkeleton={() => <BrokerLeadCardSkeleton />}
        emptyIcon="mail-open-outline"
        emptyTitle="No requests yet"
        emptyMessage="When a channel partner sends a request on one of your listings, it appears here."
        renderItem={({item}) => (
          <BrokerLeadCard
            lead={item}
            propertyName={item.property?.name}
            // The card is about the broker, so it opens the broker — the listing is
            // already the developer's own and reachable from My Listings.
            onPress={() => navigation.navigate('BrokerDetail', {leadId: item.id})}
          />
        )}
      />
    </ScreenContainer>
  );
};

export default RequestBrokersScreen;
