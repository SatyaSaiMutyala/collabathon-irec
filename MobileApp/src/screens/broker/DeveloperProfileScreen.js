import React, {useCallback, useEffect} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Badge,
  Card,
  EmptyState,
  InfoRow,
  PaginatedList,
  PropertyCard,
  PropertyCardSkeleton,
  ProfileDetailSkeleton,
  ScreenContainer,
  StatRow,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchDeveloper,
  fetchDeveloperProperties,
  selectDeveloperById,
  selectDeveloperProperties,
  selectDeveloperStatus,
} from '../../store/slices/developersSlice';
import {canLoadMore} from '../../store/paginated';
import {openLink} from '../../utils/openLink';
import {SOCIAL_ICONS} from '../../utils/socialIcons';

/**
 * A developer and their listings. The listings are their own paginated request —
 * a developer with hundreds of projects must never arrive in one payload.
 */
const DeveloperProfileScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const {developerId} = route.params;

  const developer = useAppSelector(state => selectDeveloperById(state, developerId));
  const properties = useAppSelector(state => selectDeveloperProperties(state, developerId));
  const detailStatus = useAppSelector(state => selectDeveloperStatus(state, developerId));

  const loadFirstPage = useCallback(() => {
    dispatch(fetchDeveloperProperties({developerId, page: 1}));
  }, [dispatch, developerId]);

  useEffect(() => {
    dispatch(fetchDeveloper(developerId));
    loadFirstPage();
  }, [dispatch, developerId, loadFirstPage]);

  const handleEndReached = useCallback(() => {
    if (canLoadMore(properties)) {
      dispatch(fetchDeveloperProperties({developerId, page: properties.page + 1}));
    }
  }, [dispatch, developerId, properties]);

  if (!developer) {
    // `idle` counts as loading, same reasoning as ProjectDetailScreen/
    // PropertyLeadsScreen: this screen renders once before its effect dispatches
    // (a brand new developerId starts at 'idle', not 'loading'), and that frame
    // must not read as "not found" either.
    if (detailStatus === 'loading' || detailStatus === 'idle') {
      return (
        <ScreenContainer edges={['top']}>
          <ProfileDetailSkeleton />
        </ScreenContainer>
      );
    }

    return (
      <ScreenContainer edges={['top']} style={{justifyContent: 'center'}}>
        <EmptyState
          icon="alert-circle-outline"
          title="Developer not found"
          message="This developer may have been removed or is no longer listed."
          style={{marginTop: 0}}
        />
      </ScreenContainer>
    );
  }

  // Mirrors BrokerDetailScreen's own gate on the developer side: the API starts
  // this masked and only serves the real numbers once this developer has accepted
  // a lead from the signed-in broker — nothing here un-hides anything itself.
  const visible = developer.contact_visible ?? false;

  return (
    <ScreenContainer edges={['top']}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          marginTop: spacing.sm,
          marginBottom: spacing.lg,
        }}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h3" style={{marginLeft: spacing.sm}}>
          Developer Profile
        </AppText>
      </View>

      <PaginatedList
        renderSkeleton={() => <PropertyCardSkeleton />}
        list={properties}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="business-outline"
        emptyTitle="No listings yet"
        emptyMessage="This developer has no active listings right now."
        ListHeaderComponent={
          <View style={{marginBottom: spacing.lg}}>
            <Card>
              <View style={{alignItems: 'center'}}>
                <Avatar
                  uri={developer.logo_url}
                  name={developer.company_name}
                  size="xl"
                  shape="square"
                  ringColor={developer.verified ? colors.primary : colors.border}
                  showVerified={developer.verified}
                />
                <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
                  {developer.company_name}
                </AppText>
                <AppText
                  variant="caption"
                  color={colors.textMuted}
                  style={{marginTop: moderateScale(2)}}>
                  {[developer.city, developer.state].filter(Boolean).join(', ')}
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
                    {
                      value: String(developer.properties_count ?? properties.total),
                      label: 'Properties',
                    },
                    {
                      // Commission varies per property, not per developer — it
                      // belongs on the property card, not here. Join year is
                      // developer-level and real, unlike Verified/Unverified
                      // (which used to sit here but has no admin-panel
                      // equivalent any more).
                      //
                      // One combined cell rather than a status badge under the
                      // location plus a bare year here — same green badge that
                      // used to sit under the location, just moved into this row
                      // with the year underneath it instead of split across two
                      // spots that both said something about this developer.
                      render: () => (
                        <>
                          <Badge label="Active" tone="success" />
                          {!!developer.created_at && (
                            <AppText
                              variant="caption"
                              color={colors.textMuted}
                              style={{marginTop: moderateScale(4)}}>
                              Since {new Date(developer.created_at).getFullYear()}
                            </AppText>
                          )}
                        </>
                      ),
                    },
                  ]}
                />
              </View>

              {!!developer.about && (
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
              )}
            </Card>

            {/* The rest of DeveloperResource, in the order the admin panel's Company
                panel lists it — a channel partner working a lead needs the contact
                route and the registration facts, not just the headline.

                Both reachable channels — the public contact and the internal key
                contact — stay masked (mobile/email only; name/designation are always
                shown) until this developer has accepted one of this broker's leads.
                See DeveloperResource::withContact() on the API side. */}
            <Card style={{marginTop: spacing.md, paddingVertical: spacing.xxs}}>
              {!visible && (
                <View style={styles.lockNote}>
                  <Icon name="lock-closed" size={moderateScale(15)} color={colors.warning} />
                  <AppText
                    variant="caption"
                    color={colors.textSecondary}
                    style={{marginLeft: moderateScale(8), flex: 1}}>
                    The last few digits are hidden until this developer accepts your
                    request. Accepting releases the full phone and email.
                  </AppText>
                </View>
              )}
              <InfoRow
                icon="globe-outline"
                label="Website"
                value={developer.website}
                valueColor={colors.primary}
                onPress={() => openLink(developer.website)}
              />
              <InfoRow icon="person-outline" label="Contact Person" value={developer.contact_person} />
              <InfoRow icon="briefcase-outline" label="Designation" value={developer.contact_designation} />
              <InfoRow
                icon="call-outline"
                label="Mobile"
                value={developer.mobile}
                valueColor={visible ? undefined : colors.textMuted}
              />
              <InfoRow
                icon="mail-outline"
                label="Email"
                value={developer.email}
                valueColor={visible ? undefined : colors.textMuted}
              />
              {!!developer.key_contact_person && (
                <>
                  <InfoRow icon="person-outline" label="Key Contact" value={developer.key_contact_person} />
                  <InfoRow
                    icon="briefcase-outline"
                    label="Key Contact Designation"
                    value={developer.key_contact_designation}
                  />
                  <InfoRow
                    icon="call-outline"
                    label="Key Contact Mobile"
                    value={developer.key_contact_mobile}
                    valueColor={visible ? undefined : colors.textMuted}
                  />
                  <InfoRow
                    icon="mail-outline"
                    label="Key Contact Email"
                    value={developer.key_contact_email}
                    valueColor={visible ? undefined : colors.textMuted}
                  />
                </>
              )}
              <InfoRow
                icon="location-outline"
                label="Location"
                value={
                  [developer.city, developer.state, developer.country, developer.pincode]
                    .filter(Boolean)
                    .join(', ') || null
                }
              />
              <InfoRow icon="navigate-outline" label="Address" value={developer.address} />
              {(developer.social_links ?? []).map(link => (
                <InfoRow
                  key={link.key}
                  icon={SOCIAL_ICONS[link.key] ?? 'link-outline'}
                  label={link.label}
                  value={link.value}
                  valueColor={colors.primary}
                  onPress={() => openLink(link.value)}
                />
              ))}
            </Card>

            <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
              Projects ({properties.total})
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

const styles = StyleSheet.create({
  lockNote: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingBottom: moderateScale(10),
  },
});

export default DeveloperProfileScreen;
