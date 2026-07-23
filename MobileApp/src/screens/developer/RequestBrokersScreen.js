import React from 'react';
import {FlatList, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, BrokerLeadCard, ScreenContainer} from '../../components';
import {useAppSelector} from '../../store/hooks';
import {getDeveloperById, getProjectById} from '../../data/mockDevelopers';
import {useEffectiveLeads} from '../../hooks/useDeveloperLeads';

const STATUS_ORDER = {interested: 0, accepted: 1, viewed: 2, declined: 3};

const RequestBrokersScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const developer = useAppSelector(state => state.auth.developer);
  const company = getDeveloperById(developer?.developerId);
  const projects = company?.projects ?? [];
  const leads = useEffectiveLeads(projects.map(p => p.id));
  const requested = leads.filter(l => l.effectiveStatus !== 'viewed');
  const sorted = [...requested].sort(
    (a, b) => STATUS_ORDER[a.effectiveStatus] - STATUS_ORDER[b.effectiveStatus],
  );

  return (
    <ScreenContainer edges={['top']}>
      <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
        Requests
      </AppText>

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
              No broker requests yet.
            </AppText>
          </View>
        }
      />
    </ScreenContainer>
  );
};

export default RequestBrokersScreen;
