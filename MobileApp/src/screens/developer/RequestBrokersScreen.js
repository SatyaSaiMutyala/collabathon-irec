import React, {useCallback, useEffect} from 'react';
import {useAppTheme} from '../../theme';
import {AppText, BrokerLeadCard, PaginatedList, ScreenContainer} from '../../components';
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
        emptyTitle="No broker requests yet"
        emptyMessage="When a broker marks one of your listings as Interested, it appears here."
        renderItem={({item}) => (
          <BrokerLeadCard
            lead={item}
            propertyName={item.property?.name}
            onPress={() =>
              item.property &&
              navigation.navigate('PropertyLeads', {projectId: item.property.id})
            }
          />
        )}
      />
    </ScreenContainer>
  );
};

export default RequestBrokersScreen;
