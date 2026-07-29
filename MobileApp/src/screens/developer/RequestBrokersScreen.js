import React, {useMemo, useState} from 'react';
import {FlatList, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, BrokerLeadCard, IconButton, Input, RightDrawer, ScreenContainer} from '../../components';
import {useAppSelector} from '../../store/hooks';
import {getDeveloperById, getProjectById} from '../../data/mockDevelopers';
import {getBrokerById} from '../../data/mockLeads';
import {useEffectiveLeads} from '../../hooks/useDeveloperLeads';

const STATUS_ORDER = {interested: 0, accepted: 1, viewed: 2, declined: 3};
const FILTERS = [
  {key: 'all', label: 'All Requests', tone: 'primary', description: 'Every broker interest and response'},
  {key: 'interested', label: 'Interested', tone: 'warning', description: 'Awaiting your response'},
  {key: 'accepted', label: 'Accepted', tone: 'success', description: 'Matches you’ve confirmed'},
  {key: 'declined', label: 'Declined', tone: 'danger', description: 'Leads you’ve passed on'},
];

const RequestBrokersScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const developer = useAppSelector(state => state.auth.developer);
  const company = getDeveloperById(developer?.developerId);
  const projects = company?.projects ?? [];
  const leads = useEffectiveLeads(projects.map(p => p.id));
  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [isFilterOpen, setIsFilterOpen] = useState(false);

  const requested = leads.filter(l => l.effectiveStatus !== 'viewed');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return requested.filter(lead => {
      if (statusFilter !== 'all' && lead.effectiveStatus !== statusFilter) {
        return false;
      }
      if (!q) {
        return true;
      }
      const broker = getBrokerById(lead.brokerId);
      const propertyName = getProjectById(lead.projectId)?.name ?? '';
      return (
        broker?.name?.toLowerCase().includes(q) ||
        broker?.company?.toLowerCase().includes(q) ||
        propertyName.toLowerCase().includes(q)
      );
    });
  }, [requested, query, statusFilter]);

  const sorted = [...filtered].sort(
    (a, b) => STATUS_ORDER[a.effectiveStatus] - STATUS_ORDER[b.effectiveStatus],
  );

  const filterCounts = useMemo(() => {
    const counts = {all: requested.length};
    requested.forEach(lead => {
      counts[lead.effectiveStatus] = (counts[lead.effectiveStatus] ?? 0) + 1;
    });
    return counts;
  }, [requested]);

  return (
    <ScreenContainer edges={['top']}>
      <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
        Requests
      </AppText>

      <View style={{flexDirection: 'row', alignItems: 'center'}}>
        <View style={{flex: 1}}>
          <Input
            placeholder="Search broker, company or property..."
            leftIcon="search-outline"
            value={query}
            onChangeText={setQuery}
          />
        </View>
        <View style={{marginLeft: spacing.sm, marginBottom: spacing.sm}}>
          <IconButton
            icon="options-outline"
            variant={statusFilter === 'all' ? 'outline' : 'filled'}
            onPress={() => setIsFilterOpen(true)}
          />
        </View>
      </View>

      {statusFilter !== 'all' && (
        <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: spacing.md}}>
          <AppText variant="caption" color={colors.textMuted}>
            Filtered by{' '}
          </AppText>
          <AppText variant="captionMedium" color={colors.primaryDark}>
            {FILTERS.find(f => f.key === statusFilter)?.label}
          </AppText>
          <TouchableOpacity onPress={() => setStatusFilter('all')} hitSlop={8} style={{marginLeft: spacing.xs}}>
            <Icon name="close-circle" size={moderateScale(15)} color={colors.textMuted} />
          </TouchableOpacity>
        </View>
      )}

      <FlatList
        data={sorted}
        keyExtractor={item => `${item.projectId}-${item.brokerId}`}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        renderItem={({item}) => (
          <BrokerLeadCard
            lead={item}
            propertyName={getProjectById(item.projectId)?.name}
            onPress={() => navigation.navigate('PropertyLeads', {projectId: item.projectId})}
          />
        )}
        ListEmptyComponent={
          <View style={{alignItems: 'center', marginTop: spacing.xxl}}>
            <Icon name="people-outline" size={moderateScale(36)} color={colors.textMuted} />
            <AppText variant="body" color={colors.textMuted} style={{marginTop: spacing.md}}>
              {requested.length === 0 ? 'No broker requests yet.' : 'No requests match your search.'}
            </AppText>
          </View>
        }
      />

      <RightDrawer visible={isFilterOpen} onClose={() => setIsFilterOpen(false)}>
        <View style={{paddingTop: spacing.lg, paddingHorizontal: spacing.lg, flex: 1}}>
          <View style={{flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between'}}>
            <View style={{flex: 1, marginRight: spacing.sm}}>
              <AppText variant="h3">Filter Requests</AppText>
              <AppText variant="caption" color={colors.textMuted} style={{marginTop: moderateScale(3)}}>
                Choose a status to narrow down your broker requests
              </AppText>
            </View>
            <TouchableOpacity onPress={() => setIsFilterOpen(false)} hitSlop={10}>
              <Icon name="close" size={moderateScale(22)} color={colors.textMuted} />
            </TouchableOpacity>
          </View>

          <View style={{marginTop: spacing.lg}}>
            {FILTERS.map(f => {
              const isActive = statusFilter === f.key;
              const toneColor = {
                primary: colors.primary,
                success: colors.success,
                warning: colors.warning,
                danger: colors.danger,
              }[f.tone];

              return (
                <TouchableOpacity
                  key={f.key}
                  activeOpacity={0.7}
                  onPress={() => {
                    setStatusFilter(f.key);
                    setIsFilterOpen(false);
                  }}
                  style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                    paddingVertical: spacing.md,
                    borderBottomWidth: 1,
                    borderBottomColor: colors.border,
                  }}>
                  <View
                    style={{
                      width: moderateScale(9),
                      height: moderateScale(9),
                      borderRadius: moderateScale(5),
                      backgroundColor: toneColor,
                      marginTop: moderateScale(4),
                    }}
                  />
                  <View style={{flex: 1, marginLeft: spacing.sm}}>
                    <AppText
                      variant={isActive ? 'bodyMedium' : 'body'}
                      color={isActive ? colors.textPrimary : colors.textSecondary}>
                      {f.label}
                    </AppText>
                    <AppText variant="caption" color={colors.textMuted} style={{marginTop: moderateScale(2)}}>
                      {f.description}
                    </AppText>
                  </View>
                  <AppText
                    variant="captionMedium"
                    color={isActive ? colors.primaryDark : colors.textMuted}
                    style={{marginRight: spacing.xs}}>
                    {filterCounts[f.key] ?? 0}
                  </AppText>
                  {isActive && <Icon name="checkmark" size={moderateScale(18)} color={colors.primary} />}
                </TouchableOpacity>
              );
            })}
          </View>
        </View>
      </RightDrawer>
    </ScreenContainer>
  );
};

export default RequestBrokersScreen;
