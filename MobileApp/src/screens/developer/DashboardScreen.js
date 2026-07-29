import React, {useState} from 'react';
import {ScrollView, View} from 'react-native';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Card, Chip, IconButton, PropertyCard, ScreenContainer, StatRow, TrendChart} from '../../components';
import {useAppSelector} from '../../store/hooks';
import {getDeveloperById} from '../../data/mockDevelopers';
import {getMonthlyProfileViews, weeklyProfileViews} from '../../data/mockLeads';
import {useEffectiveLeads} from '../../hooks/useDeveloperLeads';
import {useNotifications} from '../../hooks/useNotifications';

const WEEK_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTH_LABELS = ['Wk 1', 'Wk 2', 'Wk 3', 'Wk 4', 'Wk 5'];

function getRecentMonths(count = 6) {
  const months = [];
  const now = new Date();
  for (let i = count - 1; i >= 0; i -= 1) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    months.push(d.toLocaleString('en-US', {month: 'short', year: 'numeric'}));
  }
  return months;
}

const RECENT_MONTHS = getRecentMonths(6);

const DashboardScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const developer = useAppSelector(state => state.auth.developer);
  const company = getDeveloperById(developer?.developerId);
  const projects = company?.projects ?? [];
  const projectIds = projects.map(p => p.id);
  const leads = useEffectiveLeads(projectIds);
  const notifications = useNotifications();
  const unreadCount = notifications.filter(item => item.isUnread).length;
  const [trendRange, setTrendRange] = useState('week');
  const [selectedMonth, setSelectedMonth] = useState(RECENT_MONTHS[RECENT_MONTHS.length - 1]);

  const interestedCount = leads.filter(l => l.effectiveStatus === 'interested').length;
  const matchesCount = leads.filter(l => l.effectiveStatus === 'accepted').length;
  const trendData = trendRange === 'week' ? weeklyProfileViews : getMonthlyProfileViews(selectedMonth);
  const trendLabels = trendRange === 'week' ? WEEK_LABELS : MONTH_LABELS;
  const totalProfileViews = trendData.reduce((sum, v) => sum + v, 0);

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <View
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            justifyContent: 'space-between',
            marginTop: spacing.sm,
            marginBottom: spacing.lg,
          }}>
          <View style={{flexDirection: 'row', alignItems: 'center', flex: 1}}>
            <Avatar uri={company?.logo} name={company?.name} size="sm" />
            <View style={{marginLeft: spacing.sm, flex: 1}}>
              <AppText variant="caption" color={colors.textMuted}>
                Hi, {developer?.contactName?.split(' ')[0] ?? 'Developer'}
              </AppText>
              <AppText variant="h3" numberOfLines={1}>
                {company?.name ?? 'Your Company'}
              </AppText>
            </View>
          </View>
          <IconButton
            icon="notifications-outline"
            badgeCount={unreadCount}
            onPress={() => navigation.navigate('Notifications')}
          />
        </View>

        <Card style={{paddingVertical: spacing.sm}}>
          <StatRow
            stats={[
              {value: String(projects.length), label: 'Properties'},
              {value: String(interestedCount), label: 'Interested Leads'},
              {value: String(matchesCount), label: 'Matches'},
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
          <AppText variant="h3">Profile Views</AppText>
          <AppText variant="caption" color={colors.textMuted}>
            {totalProfileViews} {trendRange === 'week' ? 'this week' : `in ${selectedMonth}`}
          </AppText>
        </View>

        <View style={{flexDirection: 'row', marginBottom: spacing.xs}}>
          <Chip compact label="Week" active={trendRange === 'week'} onPress={() => setTrendRange('week')} />
          <Chip compact label="Month" active={trendRange === 'month'} onPress={() => setTrendRange('month')} />
        </View>

        {trendRange === 'month' && (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={{marginBottom: spacing.sm}}
            contentContainerStyle={{paddingRight: spacing.lg}}>
            {RECENT_MONTHS.map(month => (
              <Chip
                key={month}
                compact
                label={month}
                active={month === selectedMonth}
                onPress={() => setSelectedMonth(month)}
              />
            ))}
          </ScrollView>
        )}

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

        {projects.slice(0, 2).map(project => (
          <PropertyCard
            key={project.id}
            project={project}
            showDots={false}
            priceVariant="h2"
            onPress={() => navigation.navigate('PropertyLeads', {projectId: project.id})}
          />
        ))}

        {projects.length === 0 && (
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
