import React, {useCallback, useEffect} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Badge,
  EmptyState,
  ExpandableText,
  InfoRow,
  PaginatedList,
  ProfileDetailSkeleton,
  ProjectPreviewCard,
  ProjectPreviewCardSkeleton,
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

// Mirrors the `status` enum DeveloperController validates against (active/paused) —
// the badge used to hardcode "Active" regardless of this field, which mislabelled any
// paused developer. Kept as a lookup rather than a ternary so a third status added
// later fails visibly (undefined label) instead of silently falling into one bucket.
const STATUS_BADGE = {
  active: {label: 'Active', tone: 'success'},
  paused: {label: 'Paused', tone: 'warning'},
};

/**
 * The website row's own View/TouchableOpacity switch, same pattern InfoRow uses
 * internally — a masked value with a live `onPress` would still be tappable through to
 * `openLink()`, which is meaningless (and, worse, clickable) for a starred URL.
 */
const WebsiteWrapper = ({visible, onPress, ...rest}) =>
  visible ? <TouchableOpacity activeOpacity={0.7} onPress={onPress} {...rest} /> : <View {...rest} />;

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
  const linkColor = visible ? colors.primary : colors.textMuted;

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
        renderSkeleton={() => <ProjectPreviewCardSkeleton />}
        list={properties}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="business-outline"
        emptyTitle="No listings yet"
        emptyMessage="This developer has no active listings right now."
        ListHeaderComponent={
          <View style={{marginBottom: spacing.lg}}>
            {/* Flat, not boxed — this used to sit inside a bordered/shadowed Card, which
                stacked its own padding on top of the screen's own edge padding (a visibly
                wider inset than intended) and read as "a card floating on the screen"
                rather than the screen's own content, the way the reference layout treats
                it. The Contact & Social Details block below the stats row gets the same
                treatment — see the note there. */}
            <View>
              {/* Logo beside identity rather than centred above it — the square logo
                  reads as a mark next to a heading, not as a person's profile photo,
                  so it sits inline with the name/location column like a masthead. */}
              <View style={{flexDirection: 'row', alignItems: 'flex-start'}}>
                <Avatar
                  uri={developer.logo_url}
                  name={developer.company_name}
                  size={40}
                  shape="square"
                />
                <View style={{flex: 1, marginLeft: spacing.md}}>
                  {/* One line each, truncated rather than wrapped — with a fixed-width
                      logo box beside it, letting either line wrap is what pushed
                      "Satva Developers" onto two lines and mangled "Hyderabad,
                      Telangana" into three on a real device. DeveloperCard's row
                      (same 56pt logo) makes the same call for the same reason. */}
                  <View style={{flexDirection: 'row', alignItems: 'center'}}>
                    <AppText variant="h2" style={{flexShrink: 1}} numberOfLines={1}>
                      {developer.company_name}
                    </AppText>
                    {!!developer.verified && (
                      <Icon
                        name="checkmark-circle"
                        size={moderateScale(17)}
                        color={colors.primary}
                        style={{marginLeft: moderateScale(4)}}
                      />
                    )}
                  </View>
                  <AppText
                    variant="caption"
                    color={colors.textMuted}
                    numberOfLines={1}
                    style={{marginTop: moderateScale(4)}}>
                    {[developer.city, developer.state].filter(Boolean).join(', ')}
                  </AppText>
                  {!!STATUS_BADGE[developer.status] && (
                    <View style={{marginTop: moderateScale(6)}}>
                      <Badge {...STATUS_BADGE[developer.status]} />
                    </View>
                  )}
                </View>
              </View>

              {!!developer.about && (
                <View style={{marginTop: spacing.md}}>
                  <ExpandableText numberOfLines={3}>{developer.about}</ExpandableText>
                </View>
              )}

              {!!developer.website && (
                // Starred and inert until accepted, same as Mobile/Email/social links —
                // a plain View (not TouchableOpacity) so a masked value can't be tapped
                // through to a broken/meaningless link.
                <WebsiteWrapper
                  visible={visible}
                  onPress={() => openLink(developer.website)}
                  style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
                  <Icon name="link-outline" size={moderateScale(16)} color={linkColor} />
                  <AppText
                    variant="bodyMedium"
                    color={linkColor}
                    numberOfLines={1}
                    style={{marginLeft: moderateScale(6), flexShrink: 1}}>
                    {developer.website}
                  </AppText>
                  {visible && (
                    <Icon
                      name="open-outline"
                      size={moderateScale(13)}
                      color={linkColor}
                      style={{marginLeft: moderateScale(4)}}
                    />
                  )}
                </WebsiteWrapper>
              )}

              <View
                style={{
                  marginTop: spacing.md,
                  paddingTop: spacing.sm,
                  borderTopWidth: 1,
                  borderTopColor: colors.border,
                }}>
                <StatRow
                  stats={[
                    {
                      value: String(developer.properties_count ?? properties.total),
                      label: 'Properties',
                    },
                    // Founded/team-size aren't fields the backend sends (see
                    // DeveloperResource) — this stays the account's join year, labelled
                    // for what it actually is rather than borrowing "Founded", which
                    // would misstate a real-world fact this data doesn't have.
                    ...(developer.created_at
                      ? [
                          {
                            value: String(new Date(developer.created_at).getFullYear()),
                            label: 'Member since',
                          },
                        ]
                      : []),
                  ]}
                />
              </View>
            </View>

            {/* Back on the screen itself — the bottom sheet's ScrollView-inside-Pressable
                fought Android's touch responder for the drag gesture and scrolled
                unreliably. This whole section is one continuous list the *screen's own*
                list already scrolls (PaginatedList/FlatList), so nesting a second,
                independently-scrolling surface inside it was solving a spacing problem
                with a worse mechanism. Grouped under labels with real breathing room
                between them instead of the original cramped, unbroken run of rows. */}
            <View
              style={{
                marginTop: spacing.lg,
                paddingTop: spacing.md,
                borderTopWidth: 1,
                borderTopColor: colors.border,
              }}>
              {!visible && (
                <View style={styles.lockNote}>
                  <Icon name="lock-closed" size={moderateScale(15)} color={colors.warning} />
                  <AppText
                    variant="caption"
                    color={colors.textSecondary}
                    style={{marginLeft: moderateScale(8), flex: 1}}>
                    The last few digits are hidden and social links are locked until
                    this developer accepts your request. Accepting releases the full
                    phone, email and social links.
                  </AppText>
                </View>
              )}

              <AppText variant="overline" color={colors.textMuted} style={{marginBottom: spacing.xxs}}>
                CONTACT
              </AppText>
              <InfoRow compact icon="person-outline" label="Contact Person" value={developer.contact_person} />
              <InfoRow compact icon="briefcase-outline" label="Designation" value={developer.contact_designation} />
              <InfoRow
                compact
                icon="call-outline"
                label="Mobile"
                value={developer.mobile}
                valueColor={visible ? undefined : colors.textMuted}
              />
              <InfoRow
                compact
                icon="mail-outline"
                label="Email"
                value={developer.email}
                valueColor={visible ? undefined : colors.textMuted}
              />

              {!!developer.key_contact_person && (
                <>
                  <AppText
                    variant="overline"
                    color={colors.textMuted}
                    style={{marginTop: spacing.md, marginBottom: spacing.xxs}}>
                    KEY CONTACT
                  </AppText>
                  <InfoRow compact icon="person-outline" label="Key Contact" value={developer.key_contact_person} />
                  <InfoRow
                    compact
                    icon="briefcase-outline"
                    label="Designation"
                    value={developer.key_contact_designation}
                  />
                  <InfoRow
                    compact
                    icon="call-outline"
                    label="Mobile"
                    value={developer.key_contact_mobile}
                    valueColor={visible ? undefined : colors.textMuted}
                  />
                  <InfoRow
                    compact
                    icon="mail-outline"
                    label="Email"
                    value={developer.key_contact_email}
                    valueColor={visible ? undefined : colors.textMuted}
                  />
                </>
              )}

              <AppText
                variant="overline"
                color={colors.textMuted}
                style={{marginTop: spacing.md, marginBottom: spacing.xxs}}>
                LOCATION
              </AppText>
              <InfoRow
                compact
                icon="location-outline"
                label="Location"
                value={
                  [developer.city, developer.state, developer.country, developer.pincode]
                    .filter(Boolean)
                    .join(', ') || null
                }
              />
              <InfoRow compact icon="navigate-outline" label="Address" value={developer.address} />

              {(developer.social_links ?? []).length > 0 && (
                <>
                  <AppText
                    variant="overline"
                    color={colors.textMuted}
                    style={{marginTop: spacing.md, marginBottom: spacing.xxs}}>
                    SOCIAL LINKS
                  </AppText>
                  {developer.social_links.map(link => (
                    <InfoRow
                      compact
                      key={link.key}
                      icon={SOCIAL_ICONS[link.key] ?? 'link-outline'}
                      label={link.label}
                      value={link.value}
                      // Starred until accepted (see SocialPlatforms::linksFor on the
                      // API side) — muted and inert to match, same as Mobile/Email
                      // above, instead of a purple, tappable link pointing at a
                      // masked value.
                      valueColor={visible ? colors.primary : colors.textMuted}
                      onPress={visible ? () => openLink(link.value) : undefined}
                    />
                  ))}
                </>
              )}
            </View>

            <AppText variant="h3" style={{marginTop: spacing.xl, marginBottom: spacing.sm}}>
              Projects ({properties.total})
            </AppText>
          </View>
        }
        renderItem={({item}) => (
          <ProjectPreviewCard
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
