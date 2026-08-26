import React, {useCallback} from 'react';
import {useFocusEffect} from '@react-navigation/native';
import {BackHeader, LeadCard, PaginatedList, LeadCardSkeleton, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchLeads, fetchNextLeads} from '../../store/slices/leadsSlice';

/**
 * The requests this broker has sent — projects they marked Interested and are waiting on.
 *
 * Reads `leads.list`, which PropertyLeads and the developer inbox also write to, so the
 * fetch is on focus rather than on mount: tab screens stay mounted, and a mount-only
 * fetch would leave this showing whatever the last screen put in the list.
 *
 * Accepted requests move to the Partners tab, which has its own list — see
 * leadsSlice's `accepted`.
 */
const RequestsScreen = ({navigation}) => {
  const dispatch = useAppDispatch();
  const list = useAppSelector(state => state.leads.list);

  const loadFirstPage = useCallback(() => {
    // Only leads the broker actually acted on — a bare view isn't a request.
    dispatch(fetchLeads({page: 1, status: 'interested'}));
  }, [dispatch]);

  // Refetched on every focus, not just mount — this tab stays mounted for the life of
  // the session (React Navigation doesn't remount a tab on switch), so a mount-only
  // fetch never saw a request made from a property's own detail screen ("I'm
  // Interested") until the whole app restarted. Coming back into focus is exactly the
  // moment this list might have changed.
  useFocusEffect(
    useCallback(() => {
      loadFirstPage();
    }, [loadFirstPage]),
  );

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextLeads());
  }, [dispatch]);

  return (
    <ScreenContainer edges={['top']}>
      <BackHeader navigation={navigation} title="Requests" fallbackRoute="HomeTab" />

      <PaginatedList
        renderSkeleton={() => <LeadCardSkeleton />}
        skeletonCount={4}
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="paper-plane-outline"
        emptyTitle="No requests yet"
        emptyMessage="Listings you request appear here while you wait on the developer."
        renderItem={({item}) => (
          <LeadCard
            lead={item}
            onPress={() =>
              item.property && navigation.navigate('ProjectDetail', {projectId: item.property.id})
            }
          />
        )}
      />
    </ScreenContainer>
  );
};

export default RequestsScreen;
