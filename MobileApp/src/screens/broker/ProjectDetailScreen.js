import React from 'react';
import {ScrollView, StatusBar, StyleSheet, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Button, PropertyDetailBody, PropertyHero} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {markInterested} from '../../store/slices/leadsSlice';
import {getProjectById} from '../../data/mockDevelopers';

const ProjectDetailScreen = ({route, navigation}) => {
  const {colors, spacing, radius} = useAppTheme();
  const dispatch = useAppDispatch();
  const project = getProjectById(route.params.projectId);
  const lead = useAppSelector(state =>
    project ? state.leads.byProjectId[project.id] : undefined,
  );

  if (!project) {
    return (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
        <AppText variant="body">Project not found.</AppText>
      </View>
    );
  }

  return (
    <View style={{flex: 1, backgroundColor: colors.background}}>
      <StatusBar barStyle="light-content" translucent backgroundColor="transparent" />
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxxl}}>
        <PropertyHero project={project} onBack={() => navigation.goBack()} />
        <PropertyDetailBody project={project} />
      </ScrollView>

      <View
        style={[
          styles.footer,
          {backgroundColor: colors.card, borderTopColor: colors.border, paddingBottom: spacing.lg},
        ]}>
        {lead ? (
          <View style={[styles.leadStatus, {backgroundColor: colors.successSoft, borderRadius: radius.md}]}>
            <Icon
              name={lead.status === 'accepted' ? 'checkmark-circle' : 'time-outline'}
              size={moderateScale(18)}
              color={lead.status === 'accepted' ? colors.success : colors.warning}
            />
            <AppText
              variant="bodyMedium"
              color={lead.status === 'accepted' ? colors.success : colors.warning}
              style={{marginLeft: spacing.xs}}>
              {lead.status === 'accepted'
                ? 'Interest accepted — contact shared'
                : 'Marked as Interested — awaiting developer'}
            </AppText>
          </View>
        ) : (
          <Button
            label="Mark as Interested"
            icon="bookmark"
            onPress={() =>
              dispatch(markInterested({projectId: project.id, developerId: project.developerId}))
            }
          />
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  footer: {
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingHorizontal: moderateScale(20),
    paddingTop: moderateScale(14),
  },
  leadStatus: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: moderateScale(14),
  },
});

export default ProjectDetailScreen;
