import React, {useCallback, useEffect, useState} from 'react';
import {RefreshControl, ScrollView, View} from 'react-native';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Card,
  Chip,
  IconButton,
  PropertyCard,
  ScreenContainer,
  StatRow,
  TrendChart,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchDashboard} from '../../store/slices/dashboardSlice';
import {
  fetchDeveloperProperties,
  selectDeveloperProperties,
} from '../../store/slices/developersSlice';

/** Developer home. Figures come from /dashboard as SQL aggregates, not from a list. */
const DashboardScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const user = useAppSelector(state => state.auth.user);
  const developer = user?.developer;
  const developerId = developer?.id;

  const stats = useAppSelector(state => state.dashboard.data);
  const status = useAppSelector(state => state.dashboard.status);
  const properties = useAppSelector(state =>
    developerId ? selectDeveloperProperties(state, developerId) : null,
  );

  const [trendRange, setTrendRange] = useState('week');

  const load = useCallback(() => {
    dispatch(fetchDashboard());
    if (developerId) {
      dispatch(fetchDeveloperProperties({developerId, page: 1, per_page: 5}));
    }
  }, [dispatch, developerId]);

  useEffect(() => {
    load();
  }, [load]);

  const series =
    trendRange === 'week' ? stats?.weekly_views : stats?.monthly_views;
  const trendData = series?.values ?? [];
  const trendLabels = series?.labels ?? [];
  const totalInRange = trendData.reduce((sum, v) => sum + v, 0);

  const topProperties = (properties?.items ?? []).slice(0, 2);

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        refreshControl={
          <RefreshControl
            refreshing={status === 'loading'}
            onRefresh={load}
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
            <Avatar uri={developer?.logo_url} name={developer?.company_name} size="sm" />
            <View style={{marginLeft: spacing.sm, flex: 1}}>
              <AppText variant="caption" color={colors.textMuted}>
                Hi, {user?.name?.split(' ')[0] ?? 'Developer'}
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
          <StatRow
            stats={[
              {value: String(stats?.properties ?? 0), label: 'Properties'},
              {value: String(stats?.interested ?? 0), label: 'Interested Leads'},
              {value: String(stats?.accepted ?? 0), label: 'Matches'},
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
          <AppText variant="h3">Lead activity</AppText>
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
          <AppText variant="h3">My Properties</AppText>
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
          <View style={{alignItems: 'center', marginTop: spacing.xl}}>
            <AppText variant="body" color={colors.textMuted}>
              No properties assigned yet.
            </AppText>
          </View>
        )}
      </ScrollView>
    </ScreenContainer>
  );
};

export default DashboardScreen;
