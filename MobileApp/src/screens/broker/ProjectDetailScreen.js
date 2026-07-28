import React, {useEffect} from 'react';
import {ActivityIndicator, ScrollView, StatusBar, StyleSheet, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Button, PropertyDetailBody, PropertyHero} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchProperty,
  markInterested,
  selectPropertyById,
} from '../../store/slices/propertiesSlice';

/**
 * Property detail. Opening the screen records a view; pressing "Interested" is the
 * moment the broker's contact details unlock for the developer — the server sets
 * that flag, the client only asks for it.
 */
const ProjectDetailScreen = ({route, navigation}) => {
  const {colors, spacing, radius} = useAppTheme();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;

  const project = useAppSelector(state => selectPropertyById(state, projectId));
  const detailStatus = useAppSelector(state => state.properties.detail.status);
  const interestStatus = useAppSelector(state => state.properties.interestStatus);
  const interestError = useAppSelector(state => state.properties.interestError);

  useEffect(() => {
    dispatch(fetchProperty(projectId));
  }, [dispatch, projectId]);

  if (!project) {
    return (
      <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
        {detailStatus === 'loading' ? (
          <ActivityIndicator size="large" color={colors.primary} />
        ) : (
          <AppText variant="body">Project not found.</AppText>
        )}
      </View>
    );
  }

  const lead = project.my_lead;
  const hasInterest = !!lead && lead.status !== 'viewed';

  const statusCopy = {
    interested: 'Marked as Interested — awaiting developer',
    accepted: 'Interest accepted — contact shared',
    declined: 'The developer declined this interest',
  };

  const toneFor = status =>
    status === 'accepted' ? colors.success : status === 'declined' ? colors.danger : colors.warning;

  const iconFor = status =>
    status === 'accepted'
      ? 'checkmark-circle'
      : status === 'declined'
        ? 'close-circle'
        : 'time-outline';

  return (
    <View style={{flex: 1, backgroundColor: colors.background}}>
      <StatusBar barStyle="light-content" />
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxxl}}>
        <PropertyHero project={project} onBack={() => navigation.goBack()} />
        <PropertyDetailBody project={project} />
      </ScrollView>

      <View
        style={[
          styles.footer,
          {
            backgroundColor: colors.card,
            borderTopColor: colors.border,
            paddingBottom: spacing.lg,
          },
        ]}>
        {hasInterest ? (
          <View
            style={[
              styles.leadStatus,
              {
                backgroundColor:
                  lead.status === 'declined' ? colors.dangerSoft : colors.successSoft,
                borderRadius: radius.md,
              },
            ]}>
            <Icon
              name={iconFor(lead.status)}
              size={moderateScale(18)}
              color={toneFor(lead.status)}
            />
            <AppText
              variant="bodyMedium"
              color={toneFor(lead.status)}
              style={{marginLeft: spacing.xs}}>
              {statusCopy[lead.status] ?? 'Interest recorded'}
            </AppText>
          </View>
        ) : (
          <>
            {!!interestError && (
              <AppText
                variant="caption"
                color={colors.danger}
                style={{marginBottom: spacing.xs, textAlign: 'center'}}>
                {interestError}
              </AppText>
            )}
            <Button
              label={interestStatus === 'loading' ? 'Sending…' : 'Mark as Interested'}
              icon="bookmark"
              disabled={interestStatus === 'loading'}
              onPress={() => dispatch(markInterested(project.id))}
            />
          </>
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
