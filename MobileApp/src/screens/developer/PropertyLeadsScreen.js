import React from 'react';
import {FlatList, StatusBar, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, BrokerLeadCard, PropertyDetailBody, PropertyHero} from '../../components';
import {getProjectById} from '../../data/mockDevelopers';
import {useEffectiveLeads} from '../../hooks/useDeveloperLeads';

const PropertyLeadsScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const project = getProjectById(route.params.projectId);
  const leads = useEffectiveLeads(project ? [project.id] : []);

  if (!project) {
    return (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
        <AppText variant="body">Property not found.</AppText>
      </View>
    );
  }

  return (
    <View style={{flex: 1, backgroundColor: colors.background}}>
      <StatusBar barStyle="light-content" translucent backgroundColor="transparent" />
      <FlatList
        data={leads}
        keyExtractor={item => item.brokerId}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxxl}}
        ListHeaderComponent={
          <>
            <PropertyHero project={project} onBack={() => navigation.goBack()} />
            <PropertyDetailBody project={project} />
            <View style={{paddingHorizontal: spacing.lg}}>
              <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
                Broker Leads
              </AppText>
            </View>
          </>
        }
        renderItem={({item}) => (
          <View style={{paddingHorizontal: spacing.lg}}>
            <BrokerLeadCard
              lead={item}
              onPress={() => navigation.navigate('BrokerProfile', {brokerId: item.brokerId, projectId: project.id})}
            />
          </View>
        )}
        ListEmptyComponent={
          <View style={{alignItems: 'center', marginTop: spacing.lg, paddingHorizontal: spacing.lg}}>
            <Icon name="people-outline" size={moderateScale(36)} color={colors.textMuted} />
            <AppText variant="body" color={colors.textMuted} style={{marginTop: spacing.md}}>
              No broker activity on this property yet.
            </AppText>
          </View>
        }
      />
    </View>
  );
};

export default PropertyLeadsScreen;
