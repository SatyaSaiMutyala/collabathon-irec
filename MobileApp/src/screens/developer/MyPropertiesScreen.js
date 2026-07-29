import React, {useCallback, useEffect} from 'react';
import {View} from 'react-native';
import {useAppTheme} from '../../theme';
import {AppText, PaginatedList, PropertyCard, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {
  fetchDeveloperProperties,
  selectDeveloperProperties,
} from '../../store/slices/developersSlice';
import {canLoadMore} from '../../store/paginated';

/** The signed-in developer's own listings, paginated from the API. */
const MyPropertiesScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  // The developer profile is attached to the authenticated user by /auth/me.
  const developerId = useAppSelector(state => state.auth.user?.developer?.id);
  const list = useAppSelector(state =>
    developerId ? selectDeveloperProperties(state, developerId) : null,
  );

  const loadFirstPage = useCallback(() => {
    if (developerId) {
      dispatch(fetchDeveloperProperties({developerId, page: 1}));
    }
  }, [dispatch, developerId]);

  useEffect(() => {
    loadFirstPage();
  }, [loadFirstPage]);

  const handleEndReached = useCallback(() => {
    if (developerId && list && canLoadMore(list)) {
      dispatch(fetchDeveloperProperties({developerId, page: list.page + 1}));
    }
  }, [dispatch, developerId, list]);

  if (!developerId || !list) {
    return (
      <ScreenContainer edges={['top']}>
        <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
          My Properties
        </AppText>
        <AppText variant="body" color={colors.textMuted}>
          No developer profile is linked to this account.
        </AppText>
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer edges={['top']}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'baseline',
          justifyContent: 'space-between',
          marginTop: spacing.sm,
          marginBottom: spacing.lg,
        }}>
        <AppText variant="h1">My Properties</AppText>
        <AppText variant="caption" color={colors.textMuted}>
          {list.total} total
        </AppText>
      </View>

      <PaginatedList
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyTitle="No properties yet"
        emptyMessage="Listings assigned to you by the admin will appear here."
        renderItem={({item}) => (
          <View>
            <PropertyCard
              project={item}
              onPress={() => navigation.navigate('PropertyLeads', {projectId: item.id})}
            />
            {item.interestsCount > 0 && (
              <AppText
                variant="captionMedium"
                color={colors.primaryDark}
                style={{marginTop: -spacing.sm, marginBottom: spacing.sm}}>
                {item.interestsCount} broker{item.interestsCount === 1 ? '' : 's'} interested
              </AppText>
            )}
          </View>
        )}
      />
    </ScreenContainer>
  );
};

export default MyPropertiesScreen;
