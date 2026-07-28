import React, {useCallback, useEffect} from 'react';
import {ActivityIndicator, StatusBar, View} from 'react-native';
import {useAppTheme} from '../../theme';
import {
  AppText,
  BrokerLeadCard,
  PaginatedList,
  PropertyDetailBody,
  PropertyHero,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchProperty, selectPropertyById} from '../../store/slices/propertiesSlice';
import {fetchLeads, fetchNextLeads} from '../../store/slices/leadsSlice';

/** One listing plus every broker who touched it — viewed and interested alike. */
const PropertyLeadsScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;

  const project = useAppSelector(state => selectPropertyById(state, projectId));
  const detailStatus = useAppSelector(state => state.properties.detail.status);
  const list = useAppSelector(state => state.leads.list);

  const loadFirstPage = useCallback(() => {
    dispatch(fetchLeads({page: 1, property_id: projectId}));
  }, [dispatch, projectId]);

  useEffect(() => {
    dispatch(fetchProperty(projectId));
    loadFirstPage();
  }, [dispatch, projectId, loadFirstPage]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextLeads());
  }, [dispatch]);

  if (!project) {
    return (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
        {detailStatus === 'loading' ? (
          <ActivityIndicator size="large" color={colors.primary} />
        ) : (
          <AppText variant="body">Property not found.</AppText>
        )}
      </View>
    );
  }

  return (
    <View style={{flex: 1, backgroundColor: colors.background}}>
      <StatusBar barStyle="light-content" />
      <PaginatedList
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyTitle="No broker activity yet"
        emptyMessage="Views and interests on this listing will appear here."
        contentContainerStyle={{paddingBottom: spacing.xxxl}}
        ListHeaderComponent={
          <>
            <PropertyHero project={project} onBack={() => navigation.goBack()} />
            <PropertyDetailBody project={project} />
            <View style={{paddingHorizontal: spacing.lg}}>
              <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
                Broker Leads ({list.total})
              </AppText>
            </View>
          </>
        }
        renderItem={({item}) => (
          <View style={{paddingHorizontal: spacing.lg}}>
            <BrokerLeadCard lead={item} />
          </View>
        )}
      />
    </View>
  );
};

export default PropertyLeadsScreen;
