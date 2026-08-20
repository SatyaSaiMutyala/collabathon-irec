import React, {useCallback, useEffect} from 'react';
import {StatusBar, View} from 'react-native';
import {useContentColumn} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  BrokerLeadCard,
  EmptyState,
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
import {fetchMyProperty, selectMyPropertyById, selectMyPropertyStatus} from '../../store/slices/myPropertiesSlice';
import {
  fetchNextPropertyLeads,
  fetchPropertyLeads,
  selectPropertyLeads,
} from '../../store/slices/leadsSlice';

/** One listing plus every broker who touched it — viewed and interested alike. */
const PropertyLeadsScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const column = useContentColumn();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;

  const project = useAppSelector(state => selectMyPropertyById(state, projectId));
  const detailStatus = useAppSelector(state => selectMyPropertyStatus(state, projectId));
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
      <View style={{flex: 1, justifyContent: 'center'}}>
        <EmptyState
          icon="home-outline"
          title="Property not found"
          message="This listing may have been removed or is no longer available."
          style={{marginTop: 0}}
        />
      </View>
    );
  }

  return (
    <View style={{flex: 1, backgroundColor: colors.background}}>
      <StatusBar barStyle="light-content" />
      {/* Lays out its own root rather than using ScreenContainer (the hero is
          full-bleed), so it opts into the tablet column cap by hand — same as
          ProjectDetailScreen, which is the broker-side view of this same listing. */}
      <View style={[{flex: 1, width: '100%'}, column]}>
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
          emptyTitle="No CP requests yet"
          emptyMessage="Views and requests on this listing will appear here."
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
                  CP Requests ({list.total})
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
    </View>
  );
};

export default PropertyLeadsScreen;
