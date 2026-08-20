import React, {useEffect} from 'react';
import {ScrollView, StatusBar, StyleSheet, View} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, useContentColumn} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Button,
  EmptyState,
  PropertyDetailBody,
  PropertyDetailSkeleton,
  PropertyHero,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchProperty,
  markInterested,
  selectPropertyById,
  selectPropertyStatus,
} from '../../store/slices/propertiesSlice';

/**
 * Property detail. Opening the screen records a view; pressing "Interested" is the
 * moment the broker's contact details unlock for the developer — the server sets
 * that flag, the client only asks for it.
 */
const ProjectDetailScreen = ({route, navigation}) => {
  const {colors, spacing, radius} = useAppTheme();
  const column = useContentColumn();
  // This screen lays out its own root instead of ScreenContainer (the hero is
  // full-bleed under the status bar, so the top inset must stay unreserved) — but
  // the sticky "Mark as Interested" footer at the bottom is exactly the same shape
  // of problem ScreenContainer's SafeAreaView and the tab bar both already solve:
  // a fixed paddingBottom leaves the real system navigation bar (taller on 3-button
  // nav than on gesture nav) overlapping the footer. Reserving the real inset here
  // fixes it the same way tabBarOptions.js does for the tab bar itself.
  const insets = useSafeAreaInsets();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;

  const project = useAppSelector(state => selectPropertyById(state, projectId));
  const detailStatus = useAppSelector(state => selectPropertyStatus(state, projectId));
  const interestStatus = useAppSelector(state => state.properties.interestStatus);
  const interestError = useAppSelector(state => state.properties.interestError);

  useEffect(() => {
    dispatch(fetchProperty(projectId));
  }, [dispatch, projectId]);

  if (!project) {
    // `idle` counts as loading for the same reason PaginatedList counts it: this screen
    // renders once before its effect dispatches, and that frame must not read as
    // "not found".
    return detailStatus === 'loading' || detailStatus === 'idle' ? (
      <PropertyDetailSkeleton />
    ) : (
      <View style={{flex: 1, justifyContent: 'center'}}>
        <EmptyState
          icon="home-outline"
          title="Project not found"
          message="This listing may have been removed or is no longer available."
          style={{marginTop: 0}}
        />
      </View>
    );
  }

  const lead = project.my_lead;
  const hasInterest = !!lead && lead.status !== 'viewed';

  const statusCopy = {
    interested: 'Request Sent — Awaiting Developer Confirmation',
    accepted: 'Request accepted — contact shared',
    declined: 'The developer declined this request',
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
      {/* This screen lays out its own root rather than using ScreenContainer (the hero
          is full-bleed), so it opts into the same tablet column cap by hand. Wrapping
          the scroller *and* the sticky footer keeps the two the same width — capping
          only the content would leave the action bar running the full width of an
          iPad, detached from the column it belongs to. */}
      <View style={[styles.column, column]}>
        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{paddingBottom: spacing.xxxl}}>
          <PropertyHero project={project} onBack={() => navigation.goBack()} />
          <PropertyDetailBody project={project} highlightCommission />
        </ScrollView>

        <View
          style={[
            styles.footer,
            {
              backgroundColor: colors.card,
              borderTopColor: colors.border,
              paddingBottom: spacing.lg + insets.bottom,
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
                {statusCopy[lead.status] ?? 'Request recorded'}
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
                label={interestStatus === 'loading' ? 'Sending…' : "I'm Interested"}
                icon="bookmark"
                disabled={interestStatus === 'loading'}
                onPress={() => dispatch(markInterested(project.id))}
              />
            </>
          )}
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  column: {flex: 1, width: '100%'},
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
    // The footer's own paddingHorizontal insets this box from the *screen* edge,
    // but says nothing about the box's own tinted background having room to
    // breathe from the footer's edge — without its own horizontal padding the
    // colour fill runs flush to both sides of the box, reading as if there were
    // no gap at all even though the footer padding is there.
    paddingHorizontal: moderateScale(16),
    marginHorizontal: moderateScale(4),
  },
});

export default ProjectDetailScreen;
