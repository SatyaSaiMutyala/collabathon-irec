import React, {useCallback} from 'react';
import {View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  BackHeader,
  AppText,
  LeadCard,
  PaginatedList,
  LeadCardSkeleton,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchAcceptedLeads,
  fetchNextAcceptedLeads,
  invalidateAccepted,
  selectAcceptedLeads,
} from '../../store/slices/leadsSlice';

/**
 * The broker's side of the partnership: the projects a developer actually took them on.
 *
 * Mirrors the developer's Partners tab. The difference is which way the relationship is
 * read — a developer's partners are brokers, a broker's are developers — so this lists
 * accepted projects and puts the developer's details on each one, because the developer
 * is who the broker now has to deal with.
 *
 * Reads `leads.accepted`, its own list. Requests sits in the same tab bar reading
 * `leads.list` with a different status, and both screens stay mounted.
 */
const PartnersScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const list = useAppSelector(selectAcceptedLeads);

  const loadFirstPage = useCallback(() => {
    dispatch(fetchAcceptedLeads({page: 1}));
  }, [dispatch]);

  // Refetched on every focus, not just mount — this tab stays mounted for the life of
  // the session, so a mount-only fetch never saw a developer's accept/decline made
  // elsewhere (e.g. the app reopened after a push, or another tab) until the whole app
  // restarted. Coming back into focus is exactly the moment this list might have changed.
  useFocusEffect(
    useCallback(() => {
      loadFirstPage();

      // Same reasoning as Requests: this tab stays mounted, so returning to it would
      // otherwise open on last visit's empty panel before the refetch had started.
      return () => {
        dispatch(invalidateAccepted());
      };
    }, [loadFirstPage, dispatch]),
  );

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextAcceptedLeads());
  }, [dispatch]);

  return (
    <ScreenContainer edges={['top']}>
      <BackHeader
        navigation={navigation}
        title="Partners"
        fallbackRoute="HomeTab"
        right={
          <AppText variant="caption" color={colors.textMuted}>
            {list.total} {list.total === 1 ? 'project' : 'projects'}
          </AppText>
        }
      />

      <PaginatedList
        renderSkeleton={() => <LeadCardSkeleton />}
        skeletonCount={4}
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="people-outline"
        emptyTitle="No partnerships yet"
        emptyMessage="Once a developer accepts one of your requests, that listing and their details appear here."
        renderItem={({item}) => (
          <LeadCard
            lead={item}
            onPress={() =>
              item.property && navigation.navigate('ProjectDetail', {projectId: item.property.id})
            }
            // The whole point of an accepted lead is that the developer is now reachable,
            // so their route sits on the card rather than a tap away.
            footer={
              (item.developer?.mobile || item.developer?.email) && (
                <View
                  style={{
                    marginTop: spacing.sm,
                    paddingTop: spacing.sm,
                    borderTopWidth: 1,
                    borderTopColor: colors.border,
                  }}>
                  {!!item.developer.contact_person && (
                    <View style={styles.row}>
                      <Icon name="person-outline" size={moderateScale(13)} color={colors.primary} />
                      <AppText
                        variant="caption"
                        color={colors.textSecondary}
                        style={styles.value}
                        numberOfLines={1}>
                        {item.developer.contact_person}
                      </AppText>
                    </View>
                  )}
                  {!!item.developer.mobile && (
                    <View style={styles.row}>
                      <Icon name="call-outline" size={moderateScale(13)} color={colors.primary} />
                      <AppText
                        variant="caption"
                        color={colors.textSecondary}
                        style={styles.value}
                        numberOfLines={1}>
                        {item.developer.mobile}
                      </AppText>
                    </View>
                  )}
                  {!!item.developer.email && (
                    <View style={styles.row}>
                      <Icon name="mail-outline" size={moderateScale(13)} color={colors.primary} />
                      <AppText
                        variant="caption"
                        color={colors.textSecondary}
                        style={styles.value}
                        numberOfLines={1}>
                        {item.developer.email}
                      </AppText>
                    </View>
                  )}
                </View>
              )
            }
          />
        )}
      />
    </ScreenContainer>
  );
};

const styles = {
  heading: spacing => ({
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    marginTop: spacing.sm,
    marginBottom: spacing.lg,
  }),
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: moderateScale(5),
  },
  value: {
    marginLeft: moderateScale(6),
    flex: 1,
  },
};

export default PartnersScreen;
