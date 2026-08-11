import React, {useCallback, useEffect} from 'react';
import {useAppTheme} from '../../theme';
import {AppText, LeadCard, PaginatedList, LeadCardSkeleton, ScreenContainer} from '../../components';
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
  const {spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const list = useAppSelector(state => state.leads.list);

  const loadFirstPage = useCallback(() => {
    // Only leads the broker actually acted on — a bare view isn't a request.
    dispatch(fetchLeads({page: 1, status: 'interested'}));
  }, [dispatch]);

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
        renderSkeleton={() => <LeadCardSkeleton />}
        skeletonCount={4}
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="paper-plane-outline"
        emptyTitle="No requests yet"
        emptyMessage="Listings you mark as Interested appear here while you wait on the developer."
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
