import React, {useEffect, useState} from 'react';
import {ScrollView, StatusBar, StyleSheet, TouchableOpacity, View} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, useContentColumn} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Button,
  EmptyState,
  ProjectDetailsTab,
  ProjectFilesSalesTab,
  ProjectLocationTab,
  ProjectOverviewTab,
  PropertyDetailSkeleton,
  TabBar,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchProperty,
  markInterested,
  selectPropertyById,
  selectPropertyStatus,
} from '../../store/slices/propertiesSlice';

const TABS = ['Overview', 'Details', 'Location', 'Files & Sales'];

/**
 * Property detail. Opening the screen records a view; pressing "Interested" is the
 * moment the broker's contact details unlock for the developer — the server sets
 * that flag, the client only asks for it.
 *
 * Four tabs (Overview/Details/Location/Files & Sales) instead of one long scroll —
 * header and tab strip are fixed above the ScrollView, not inside it, so switching
 * tabs never has to fight the previous tab's scroll position; the `key` on the
 * ScrollView below resets it to the top on every switch, matching a native tab view.
 */
const ProjectDetailScreen = ({route, navigation}) => {
  const {colors, spacing, radius} = useAppTheme();
  const column = useContentColumn();
  const insets = useSafeAreaInsets();
  const dispatch = useAppDispatch();
  const {projectId} = route.params;
  const [activeTab, setActiveTab] = useState(0);

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
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />
      {/* This screen lays out its own root rather than using ScreenContainer, so it
          opts into the same tablet column cap by hand. Wrapping the header, tabs,
          scroller and sticky footer all in it keeps every one of them the same width
          — capping only the content would leave the rest running the full width of
          an iPad, detached from the column they belong to. */}
      <View style={[styles.column, column, {paddingTop: insets.top}]}>
        <View style={{flexDirection: 'row', alignItems: 'center', paddingHorizontal: spacing.lg, paddingVertical: spacing.sm}}>
          <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
            <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
          </TouchableOpacity>
          <AppText variant="h3" numberOfLines={1} style={{flex: 1, marginLeft: spacing.sm, marginRight: spacing.xl}}>
            {project.name}
          </AppText>
        </View>

        <TabBar tabs={TABS} activeIndex={activeTab} onChange={setActiveTab} />

        <ScrollView
          key={activeTab}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{paddingBottom: spacing.xxxl}}>
          {activeTab === 0 && <ProjectOverviewTab project={project} highlightCommission />}
          {activeTab === 1 && <ProjectDetailsTab project={project} />}
          {activeTab === 2 && <ProjectLocationTab project={project} />}
          {activeTab === 3 && <ProjectFilesSalesTab project={project} />}
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
