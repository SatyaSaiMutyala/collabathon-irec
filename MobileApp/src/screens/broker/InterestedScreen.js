import React, {useMemo} from 'react';
import {FlatList, Image, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Badge, Card, ScreenContainer} from '../../components';
import {useAppSelector} from '../../store/hooks';
import {getDeveloperById, getProjectById} from '../../data/mockDevelopers';

const InterestedScreen = ({navigation}) => {
  const {colors, spacing, radius} = useAppTheme();
  const leadsByProject = useAppSelector(state => state.leads.byProjectId);

  const leads = useMemo(() => {
    return Object.values(leadsByProject)
      .map(lead => {
        const project = getProjectById(lead.projectId);
        const developer = getDeveloperById(lead.developerId);
        if (!project || !developer) {
          return null;
        }
        return {lead, project, developer};
      })
      .filter(Boolean)
      .sort((a, b) => (a.lead.markedAt < b.lead.markedAt ? 1 : -1));
  }, [leadsByProject]);

  return (
    <ScreenContainer edges={['top']}>
      <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
        Interested
      </AppText>

      <FlatList
        data={leads}
        keyExtractor={item => item.project.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        ItemSeparatorComponent={() => <View style={{height: moderateScale(12)}} />}
        renderItem={({item}) => (
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={() => navigation.navigate('ProjectDetail', {projectId: item.project.id})}>
            <Card>
              <View style={{flexDirection: 'row', alignItems: 'center'}}>
                <Image
                  source={{uri: item.project.coverImage}}
                  style={{
                    width: moderateScale(64),
                    height: moderateScale(64),
                    borderRadius: radius.md,
                  }}
                />
                <View style={{flex: 1, marginLeft: spacing.md}}>
                  <AppText variant="h3" numberOfLines={1}>
                    {item.project.name}
                  </AppText>
                  <AppText
                    variant="caption"
                    color={colors.textSecondary}
                    style={{marginTop: moderateScale(2)}}>
                    {item.developer.name}
                  </AppText>
                  <View style={{marginTop: spacing.xs}}>
                    <Badge
                      label={item.lead.status === 'accepted' ? 'Accepted' : 'Pending'}
                      tone={item.lead.status === 'accepted' ? 'success' : 'warning'}
                    />
                  </View>
                </View>
                <Icon name="chevron-forward" size={moderateScale(18)} color={colors.textMuted} />
              </View>
            </Card>
          </TouchableOpacity>
        )}
        ListEmptyComponent={
          <View style={{alignItems: 'center', marginTop: spacing.xxxl}}>
            <Icon name="bookmark-outline" size={moderateScale(40)} color={colors.textMuted} />
            <AppText
              variant="body"
              color={colors.textMuted}
              align="center"
              style={{marginTop: spacing.md, maxWidth: moderateScale(240)}}>
              Projects you mark as Interested will show up here.
            </AppText>
          </View>
        }
      />
    </ScreenContainer>
  );
};

export default InterestedScreen;
