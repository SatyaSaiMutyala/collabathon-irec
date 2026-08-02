import React, {useCallback, useEffect} from 'react';
import {StatusBar, View} from 'react-native';
import {useAppTheme} from '../../theme';
import {
  AppText,
  BrokerLeadCard,
  PaginatedList,
  BrokerLeadCardSkeleton,
  PropertyDetailSkeleton,
  PropertyDetailBody,
  PropertyHero,
  ProjectDecisionPanel,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
// Deliberately the developer's own endpoint, not the broker one: /properties/{id}
// 404s for a project still awaiting this developer's acceptance, which is exactly the
// project they most need to open.
import {fetchMyProperty, selectMyPropertyById} from '../../store/slices/myPropertiesSlice';
import {
  fetchNextPropertyLeads,
  fetchPropertyLeads,
  selectPropertyLeads,
} from '../../store/slices/leadsSlice';

/** One listing plus every broker who touched it — viewed and interested alike. */
const PropertyLeadsScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;

  const project = useAppSelector(state => selectMyPropertyById(state, projectId));
  const detailStatus = useAppSelector(state => state.myProperties.detail.status);
  const list = useAppSelector(selectPropertyLeads);

  const loadFirstPage = useCallback(() => {
    dispatch(fetchPropertyLeads({propertyId: projectId, page: 1}));
  }, [dispatch, projectId]);

  useEffect(() => {
    dispatch(fetchMyProperty(projectId));
    loadFirstPage();
  }, [dispatch, projectId, loadFirstPage]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextPropertyLeads());
  }, [dispatch]);

  if (!project) {
    // `idle` counts as loading for the same reason PaginatedList counts it: this screen
    // renders once before its effect dispatches, and that frame must not read as
    // "not found".
    return detailStatus === 'loading' || detailStatus === 'idle' ? (
      <PropertyDetailSkeleton />
    ) : (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
        <AppText variant="body">Property not found.</AppText>
      </View>
    );
  }

  return (
    <View style={{flex: 1, backgroundColor: colors.background}}>
      <StatusBar barStyle="light-content" />
      <PaginatedList
        renderSkeleton={() => (
          <View style={{paddingHorizontal: spacing.lg}}>
            <BrokerLeadCardSkeleton />
          </View>
        )}
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="people-outline"
        emptyTitle="No broker activity yet"
        emptyMessage="Views and interests on this listing will appear here."
        contentContainerStyle={{paddingBottom: spacing.xxxl}}
        ListHeaderComponent={
          <>
            <PropertyHero project={project} onBack={() => navigation.goBack()} />
            <PropertyDetailBody project={project} />

            {/* Closes out the project details: the developer decides here, having just
                read the sheet above. Everything below is lead activity. */}
            <View style={{paddingHorizontal: spacing.lg}}>
              <ProjectDecisionPanel project={project} />
            </View>

            <View style={{paddingHorizontal: spacing.lg}}>
              <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
                Broker Leads ({list.total})
              </AppText>
            </View>
          </>
        }
        renderItem={({item}) => (
          <View style={{paddingHorizontal: spacing.lg}}>
            <BrokerLeadCard
              lead={item}
              onPress={() => navigation.navigate('BrokerDetail', {leadId: item.id})}
            />
          </View>
        )}
      />
    </View>
  );
};

export default PropertyLeadsScreen;
