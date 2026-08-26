import React, {useCallback, useEffect} from 'react';
import {
  BackHeader,
  BrokerLeadCard,
  BrokerLeadCardSkeleton,
  PaginatedList,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchLeads, fetchNextLeads, invalidateList} from '../../store/slices/leadsSlice';
import {useRefreshOnFocus} from '../../hooks/useRefreshOnFocus';

/**
 * The developer's inbox. Only leads that reached "interested" are actionable, so
 * that is what the server is asked for — a bare view is not a request.
 */
const RequestBrokersScreen = ({navigation}) => {
  const dispatch = useAppDispatch();
  const list = useAppSelector(state => state.leads.list);

  const loadFirstPage = useCallback(() => {
    dispatch(fetchLeads({page: 1, status: 'interested'}));
  }, [dispatch]);

  // Opening fetch on mount; every later focus refreshes on top of it.
  //
  // Mount-only was the previous choice, to protect the scroll position when coming back
  // from a broker's detail screen — but responding to a request is exactly what happens
  // on that detail screen, so the inbox was guaranteed to be wrong on return and only a
  // manual pull would fix it. The scroll position survives anyway: page 1 replaces the
  // items in place and the FlatList is never remounted, so it keeps its offset.
  useEffect(() => {
    loadFirstPage();
  }, [loadFirstPage]);

  const invalidate = useCallback(() => dispatch(invalidateList()), [dispatch]);

  useRefreshOnFocus(loadFirstPage, invalidate);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextLeads());
  }, [dispatch]);

  return (
    <ScreenContainer edges={['top']}>
      <BackHeader navigation={navigation} title="Requests" fallbackRoute="DashboardTab" />

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
