import React, {useCallback, useEffect, useState} from 'react';
import {RefreshControl, ScrollView, View} from 'react-native';
import {useAppTheme} from '../../theme';
import {firstName, greeting} from '../../utils/name';
import {
  EmptyState,
  AppText,
  Avatar,
  Card,
  Chip,
  IconButton,
  PropertyCard,
  ScreenContainer,
  StatRow,
  TrendChart,
  DashboardSkeleton,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {useRefreshOnFocus} from '../../hooks/useRefreshOnFocus';
import {fetchDashboard} from '../../store/slices/dashboardSlice';
// The developer's own inventory, not the public view of themselves — the dashboard
// must surface projects still awaiting their acceptance, and the public endpoint
// filters exactly those out.
import {
  fetchMyProperties,
  fetchMyPropertiesSummary,
  selectMyProperties,
  selectMyPropertiesSummary,
} from '../../store/slices/myPropertiesSlice';

/** Developer home. Figures come from /dashboard as SQL aggregates, not from a list. */
const DashboardScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const user = useAppSelector(state => state.auth.user);
  const developer = user?.developer;
  const developerId = developer?.id;

  const stats = useAppSelector(state => state.dashboard.data);
  const status = useAppSelector(state => state.dashboard.status);
  const properties = useAppSelector(selectMyProperties);
  const inventory = useAppSelector(selectMyPropertiesSummary);

  const [trendRange, setTrendRange] = useState('week');

  const load = useCallback(() => {
    dispatch(fetchDashboard());
    if (developerId) {
      dispatch(fetchMyProperties({page: 1, per_page: 5}));
      dispatch(fetchMyPropertiesSummary());
    }
  }, [dispatch, developerId]);

  useEffect(() => {
    load();
  }, [load]);

  // The figures here are downstream of every action taken on the other tabs — accepting
  // a request changes the counts and the inventory summary — so they are re-asked for on
  // every return rather than only at mount. The board keeps its current numbers on
  // screen while that happens; see the skeleton guard below.
  useRefreshOnFocus(load);

  // The spinner follows the user's own pull, never the focus refresh above — bound to
  // `status` it would drop in and retract on every return to this tab, which is the same
  // involuntary jump PaginatedList had. Released when the fetch settles either way, so a
  // failed refresh does not leave it spinning.
  const [isUserRefreshing, setIsUserRefreshing] = useState(false);

  const handleRefresh = useCallback(() => {
    setIsUserRefreshing(true);
    load();
  }, [load]);

  useEffect(() => {
    if (isUserRefreshing && status !== 'loading') {
      setIsUserRefreshing(false);
    }
  }, [isUserRefreshing, status]);

  const series =
    trendRange === 'week' ? stats?.weekly_views : stats?.monthly_views;
  const trendData = series?.values ?? [];
  const trendLabels = series?.labels ?? [];
  const totalInRange = trendData.reduce((sum, v) => sum + v, 0);

  const topProperties = (properties?.items ?? []).slice(0, 2);

  // Skeleton only when there is nothing to show yet. On a refresh the real dashboard
  // stays put and the RefreshControl carries the feedback — swapping a populated screen
  // back to a skeleton reads as data loss.
  if ((status === 'loading' || status === 'idle') && !stats) {
    return (
      <ScreenContainer edges={['top']}>
        <DashboardSkeleton />
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        refreshControl={
          <RefreshControl
            refreshing={isUserRefreshing}
            onRefresh={handleRefresh}
            tintColor={colors.primary}
          />
        }>
        <View
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            justifyContent: 'space-between',
            marginTop: spacing.sm,
            marginBottom: spacing.lg,
          }}>
          <View style={{flexDirection: 'row', alignItems: 'center', flex: 1}}>
            <Avatar uri={developer?.logo_url} name={developer?.company_name} size="sm" shape="square" />
            <View style={{marginLeft: spacing.sm, flex: 1}}>
              <AppText variant="caption" color={colors.textMuted}>
                {greeting()}, {firstName(user?.name, 'Developer')}
              </AppText>
              <AppText variant="h3" numberOfLines={1}>
                {developer?.company_name ?? 'Your Company'}
              </AppText>
            </View>
          </View>
          <IconButton
            icon="notifications-outline"
            badgeCount={stats?.interested ?? 0}
            onPress={() => navigation.navigate('Notifications')}
          />
        </View>

        <Card style={{paddingVertical: spacing.sm}}>
          {/* When projects are waiting on this developer, that is the most actionable
              number on the screen, so it replaces the passive "Properties" count. */}
          <StatRow
            stats={[
              inventory?.pending
                ? {
                    value: String(inventory.pending),
                    label: 'Awaiting you',
                    onPress: () => navigation.navigate('PropertiesTab'),
                  }
                : {
                    value: String(inventory?.live ?? stats?.properties ?? 0),
                    label: 'Active Listings',
                    onPress: () => navigation.navigate('PropertiesTab'),
                  },
              {
                value: String(stats?.interested ?? 0),
                label: 'CP Requests',
                onPress: () => navigation.navigate('RequestBrokersTab'),
              },
              {
                value: String(stats?.accepted ?? 0),
                label: 'Introductions',
                onPress: () => navigation.navigate('PartnersTab'),
              },
            ]}
          />
        </Card>

        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginTop: spacing.xl,
            marginBottom: spacing.xs,
          }}>
          <AppText variant="h3">CP Engagement</AppText>
          <AppText variant="caption" color={colors.textMuted}>
            {totalInRange} {trendRange === 'week' ? 'this week' : 'in 5 weeks'}
          </AppText>
        </View>

        <View style={{flexDirection: 'row', marginBottom: spacing.xs}}>
          <Chip
            compact
            label="Week"
            active={trendRange === 'week'}
            onPress={() => setTrendRange('week')}
          />
          <Chip
            compact
            label="Month"
            active={trendRange === 'month'}
            onPress={() => setTrendRange('month')}
          />
        </View>

        <Card>
          <TrendChart data={trendData} labels={trendLabels} />
        </Card>

        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginTop: spacing.xl,
            marginBottom: spacing.sm,
          }}>
          <AppText variant="h3">My Listings</AppText>
          <AppText
            variant="captionMedium"
            color={colors.primary}
            onPress={() => navigation.navigate('PropertiesTab')}>
            View All
          </AppText>
        </View>

        {topProperties.map(project => (
          <PropertyCard
            key={project.id}
            project={project}
            showDots={false}
            priceVariant="h2"
            onPress={() => navigation.navigate('PropertyLeads', {projectId: project.id})}
          />
        ))}

        {topProperties.length === 0 && (
          <EmptyState
            icon="business-outline"
            title="No listings yet"
            message="Listings our team assigns to you appear here once you accept them."
          />
        )}
      </ScrollView>
    </ScreenContainer>
  );
};

export default DashboardScreen;
