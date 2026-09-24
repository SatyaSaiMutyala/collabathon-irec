import React, {useCallback, useEffect, useState} from 'react';
import {StatusBar, TouchableOpacity, View} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, useContentColumn} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  BrokerLeadCard,
  EmptyState,
  PaginatedList,
  BrokerLeadCardSkeleton,
  PropertyDetailSkeleton,
  ProjectDetailsTab,
  ProjectFilesSalesTab,
  ProjectLocationTab,
  ProjectOverviewTab,
  ProjectDecisionPanel,
  TabBar,
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

const TABS = ['Overview', 'Details', 'Location', 'Files & Sales'];

/**
 * One listing plus every broker who touched it — viewed and interested alike.
 *
 * The listing's own info is the same four-tab Overview/Details/Location/Files & Sales
 * view ProjectDetailScreen shows a browsing broker (this developer's own project, same
 * data, same components) — the decision panel and the CP Requests list below it are
 * what's specific to owning the listing, so they sit after the tabs rather than inside
 * one of them.
 */
const PropertyLeadsScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const column = useContentColumn();
  const insets = useSafeAreaInsets();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;
  const [activeTab, setActiveTab] = useState(0);

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
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />
      {/* Lays out its own root rather than using ScreenContainer, so it opts into the
          tablet column cap by hand — same as ProjectDetailScreen, which shows this
          same listing's info to a browsing broker. */}
      <View style={[{flex: 1, width: '100%'}, column, {paddingTop: insets.top}]}>
        <View style={{flexDirection: 'row', alignItems: 'center', paddingHorizontal: spacing.lg, paddingVertical: spacing.sm}}>
          <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
            <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
          </TouchableOpacity>
          <AppText variant="h3" numberOfLines={1} style={{flex: 1, marginLeft: spacing.sm, marginRight: spacing.xl}}>
            {project.name}
          </AppText>
        </View>

        <TabBar tabs={TABS} activeIndex={activeTab} onChange={setActiveTab} />

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
              {activeTab === 0 && <ProjectOverviewTab project={project} />}
              {activeTab === 1 && <ProjectDetailsTab project={project} />}
              {activeTab === 2 && <ProjectLocationTab project={project} />}
              {activeTab === 3 && <ProjectFilesSalesTab project={project} />}

              {/* Closes out the project details: the developer decides here, having just
                  read the tabs above. Everything below is lead activity. */}
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
