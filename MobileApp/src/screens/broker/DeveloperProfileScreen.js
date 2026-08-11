import React, {useCallback, useEffect} from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Card,
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
  const detailStatus = useAppSelector(state => state.developers.detail.status);

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

  if (!developer && detailStatus === 'loading') {
    return (
      <ScreenContainer edges={['top']}>
        <ProfileDetailSkeleton />
      </ScreenContainer>
    );
  }

  if (!developer) {
    return (
      <ScreenContainer edges={['top']}>
        <AppText variant="body">Developer not found.</AppText>
      </ScreenContainer>
    );
  }

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
                  ringColor={developer.verified ? colors.primary : colors.border}
                  showVerified={developer.verified}
                />
                <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
                  {developer.company_name}
                </AppText>
                {/* Where they are, not what they are registered as — RERA has its own
                    row below now, and printing it twice made the sub-line wrap. */}
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
                    developer.verified
                      ? {icon: 'shield-checkmark', iconTone: 'success', label: 'Verified'}
                      : {icon: 'shield-outline', iconTone: 'muted', label: 'Unverified'},
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

                The key contact (person, designation, mobile, email) is the one part of
                the admin record that never appears here: it is the internal relationship
                owner, and DeveloperResource does not send it. */}
            <Card style={{marginTop: spacing.md, paddingVertical: spacing.xxs}}>
              <InfoRow icon="shield-checkmark-outline" label="RERA / Licence" value={developer.rera_number} />
              <InfoRow
                icon="globe-outline"
                label="Website"
                value={developer.website}
                valueColor={colors.primary}
                onPress={() => openLink(developer.website)}
              />
              <InfoRow icon="person-outline" label="Contact Person" value={developer.contact_person} />
              <InfoRow icon="briefcase-outline" label="Designation" value={developer.contact_designation} />
              <InfoRow icon="call-outline" label="Mobile" value={developer.mobile} />
              <InfoRow icon="mail-outline" label="Email" value={developer.email} />
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
              <InfoRow
                icon="pulse-outline"
                label="Account Status"
                value={developer.status ? developer.status[0].toUpperCase() + developer.status.slice(1) : null}
                valueColor={developer.status === 'active' ? colors.success : colors.textPrimary}
              />
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

export default DeveloperProfileScreen;
