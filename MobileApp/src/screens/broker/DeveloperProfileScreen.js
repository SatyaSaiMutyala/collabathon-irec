import React, {useCallback, useEffect} from 'react';
import {ActivityIndicator, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Card,
  PaginatedList,
  PropertyCard,
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
        <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
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

  const payout = Number(developer.cp_payout_percent ?? 0);

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
        list={properties}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyTitle="No listings yet"
        emptyMessage="This developer has no active properties right now."
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
                <AppText
                  variant="caption"
                  color={colors.textMuted}
                  style={{marginTop: moderateScale(2)}}>
                  {developer.city}
                  {developer.rera_number ? ` · RERA ${developer.rera_number}` : ''}
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
                    {value: `${payout % 1 === 0 ? payout : payout.toFixed(2)}%`, label: 'CP Payout'},
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
