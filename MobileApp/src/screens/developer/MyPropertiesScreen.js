import React from 'react';
import {FlatList, View} from 'react-native';
import {useAppTheme} from '../../theme';
import {AppText, PropertyCard, ScreenContainer} from '../../components';
import {useAppSelector} from '../../store/hooks';
import {getDeveloperById} from '../../data/mockDevelopers';
import {useEffectiveLeads} from '../../hooks/useDeveloperLeads';

const MyPropertiesScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const developer = useAppSelector(state => state.auth.developer);
  const company = getDeveloperById(developer?.developerId);
  const projects = company?.projects ?? [];
  const leads = useEffectiveLeads(projects.map(p => p.id));

  const interestedCountFor = projectId =>
    leads.filter(l => l.projectId === projectId && l.effectiveStatus === 'interested').length;

  return (
    <ScreenContainer edges={['top']}>
      <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
        My Properties
      </AppText>

      <FlatList
        data={projects}
        keyExtractor={item => item.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        renderItem={({item}) => {
          const interested = interestedCountFor(item.id);
          return (
            <View>
              <PropertyCard
                project={item}
                onPress={() => navigation.navigate('PropertyLeads', {projectId: item.id})}
              />
              {interested > 0 && (
                <AppText
                  variant="captionMedium"
                  color={colors.primaryDark}
                  style={{marginTop: -spacing.sm, marginBottom: spacing.sm}}>
                  {interested} broker{interested === 1 ? '' : 's'} interested
                </AppText>
              )}
            </View>
          );
        }}
        ListEmptyComponent={
          <View style={{alignItems: 'center', marginTop: spacing.xxl}}>
            <AppText variant="body" color={colors.textMuted}>
              No properties assigned yet.
            </AppText>
          </View>
        }
      />
    </ScreenContainer>
  );
};

export default MyPropertiesScreen;
