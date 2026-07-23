import React from 'react';
import {FlatList, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Card, PropertyCard, ScreenContainer, StatRow} from '../../components';
import {getDeveloperById} from '../../data/mockDevelopers';

const DeveloperProfileScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const developer = getDeveloperById(route.params.developerId);

  if (!developer) {
    return (
      <ScreenContainer edges={['top']}>
        <AppText variant="body">Developer not found.</AppText>
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer edges={['top']}>
      <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm, marginBottom: spacing.lg}}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h3" style={{marginLeft: spacing.sm}}>
          Developer Profile
        </AppText>
      </View>

      <FlatList
        data={developer.projects}
        keyExtractor={item => item.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        ListHeaderComponent={
          <View style={{marginBottom: spacing.lg}}>
            <Card>
              <View style={{alignItems: 'center'}}>
                <Avatar
                  uri={developer.logo}
                  name={developer.name}
                  size="xl"
                  ringColor={developer.verified ? colors.primary : colors.border}
                  showVerified={developer.verified}
                />
                <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
                  {developer.name}
                </AppText>
                <AppText variant="caption" color={colors.textMuted} style={{marginTop: moderateScale(2)}}>
                  {developer.city} · RERA {developer.reraNumber}
                </AppText>
              </View>

              <View
                style={{
                  marginTop: spacing.lg,
                  paddingTop: spacing.md,
                  borderTopWidth: 1,
                  borderTopColor: colors.border,
                }}>
                <StatRow
                  stats={[
                    {value: String(developer.projects.length), label: 'Properties'},
                    {value: `${developer.cpPayoutPercent}%`, label: 'CP Payout'},
                    developer.verified
                      ? {icon: 'shield-checkmark', iconTone: 'success', label: 'Verified'}
                      : {icon: 'shield-outline', iconTone: 'muted', label: 'Unverified'},
                  ]}
                />
              </View>

              <View
                style={{
                  marginTop: spacing.md,
                  paddingTop: spacing.md,
                  borderTopWidth: 1,
                  borderTopColor: colors.border,
                }}>
                <AppText variant="body" color={colors.textSecondary}>
                  {developer.about}
                </AppText>
              </View>
            </Card>

            <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
              Projects ({developer.projects.length})
            </AppText>
          </View>
        }
        renderItem={({item}) => (
          <PropertyCard
            project={item}
            onPress={() => navigation.navigate('ProjectDetail', {projectId: item.id})}
          />
        )}
      />
    </ScreenContainer>
  );
};

export default DeveloperProfileScreen;
