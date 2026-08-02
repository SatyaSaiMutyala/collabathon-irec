import React, {useCallback, useEffect} from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
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

  // On mount, not on focus — `leads.accepted` has one writer, so coming back from a
  // project would otherwise reload the list and lose where the user was.
  useEffect(() => {
    loadFirstPage();
  }, [loadFirstPage]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextAcceptedLeads());
  }, [dispatch]);

  return (
    <ScreenContainer edges={['top']}>
      <View style={styles.heading(spacing)}>
        <AppText variant="h1">Partners</AppText>
        <AppText variant="caption" color={colors.textMuted}>
          {list.total} {list.total === 1 ? 'project' : 'projects'}
        </AppText>
      </View>

      <PaginatedList
        renderSkeleton={() => <LeadCardSkeleton />}
        skeletonCount={4}
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="people-outline"
        emptyTitle="No partnerships yet"
        emptyMessage="Once a developer accepts one of your requests, that project and their details appear here."
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
                  {!!item.developer.cp_payout_percent && (
                    <View style={styles.row}>
                      <Icon name="pricetag-outline" size={moderateScale(13)} color={colors.primary} />
                      <AppText
                        variant="caption"
                        color={colors.textSecondary}
                        style={styles.value}>
                        {item.developer.cp_payout_percent}% channel partner payout
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
