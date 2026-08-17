import React, {useCallback, useEffect, useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {firstName} from '../../utils/name';
import {
  AppText,
  Avatar,
  Card,
  Chip,
  DeveloperCard,
  IconButton,
  Input,
  LocationPickerSheet,
  PaginatedList,
  DeveloperCardSkeleton,
  ScreenContainer,
  StatRow,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchDevelopers, fetchNextDevelopers} from '../../store/slices/developersSlice';
// Same /dashboard endpoint the developer side's board reads — DashboardController
// branches on the signed-in role, so this broker token gets {requests_sent,
// associations} instead of the developer's listing/lead breakdown.
import {fetchDashboard} from '../../store/slices/dashboardSlice';
import {useCurrentLocation} from '../../hooks/useLocation';
import {useDebouncedValue} from '../../hooks/useDebouncedValue';

/**
 * Developer directory. Search and city filtering are sent to the API — this screen
 * holds only the pages it has fetched, never the whole table.
 */
const HomeScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const user = useAppSelector(state => state.auth.user);
  const list = useAppSelector(state => state.developers.list);
  const stats = useAppSelector(state => state.dashboard.data);

  const [query, setQuery] = useState('');
  const [isPickerVisible, setIsPickerVisible] = useState(false);
  const [manualCity, setManualCity] = useState(null);
  const location = useCurrentLocation();

  // One request per typing pause, not one per keystroke.
  const debouncedQuery = useDebouncedValue(query, 400);
  const activeCity = manualCity ?? null;

  useEffect(() => {
    location.detectLocation();
    dispatch(fetchDashboard());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadFirstPage = useCallback(() => {
    dispatch(
      fetchDevelopers({
        page: 1,
        search: debouncedQuery.trim() || undefined,
        city: activeCity || undefined,
      }),
    );
  }, [dispatch, debouncedQuery, activeCity]);

  // Any change to search or city restarts at page 1.
  useEffect(() => {
    loadFirstPage();
  }, [loadFirstPage]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextDevelopers());
  }, [dispatch]);

  const goToDeveloper = id => navigation.navigate('DeveloperProfile', {developerId: id});

  return (
    <ScreenContainer edges={['top']}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginTop: spacing.sm,
          marginBottom: spacing.lg,
        }}>
        <View style={{flexDirection: 'row', alignItems: 'center', flex: 1}}>
          <Avatar uri={user?.avatar_url} name={user?.name} size="sm" />
          <View style={{marginLeft: spacing.sm, flex: 1}}>
            <AppText variant="caption" color={colors.textMuted}>
              Hi, {firstName(user?.name, 'Broker')}
            </AppText>
            <TouchableOpacity
              activeOpacity={0.75}
              onPress={() => setIsPickerVisible(true)}
              style={{flexDirection: 'row', alignItems: 'center', marginTop: moderateScale(1)}}>
              <Icon name="location" size={moderateScale(14)} color={colors.primary} />
              <AppText
                variant="bodyMedium"
                weight="semiBold"
                numberOfLines={1}
                style={{marginLeft: moderateScale(4), maxWidth: moderateScale(160)}}>
                {activeCity ?? (location.isLoading ? 'Locating…' : location.label)}
              </AppText>
              <Icon
                name="chevron-down"
                size={moderateScale(14)}
                color={colors.textMuted}
                style={{marginLeft: moderateScale(2)}}
              />
            </TouchableOpacity>
          </View>
        </View>
        <IconButton
          icon="notifications-outline"
          onPress={() => navigation.navigate('Notifications')}
        />
      </View>

      <Card style={{paddingVertical: spacing.sm, marginBottom: spacing.lg}}>
        <StatRow
          stats={[
            {
              value: String(stats?.requests_sent ?? 0),
              label: 'Requests Sent',
              onPress: () => navigation.navigate('RequestsTab'),
            },
            {
              value: String(stats?.associations ?? 0),
              label: 'Associations',
              onPress: () => navigation.navigate('PartnersTab'),
            },
          ]}
        />
      </Card>

      <Input
        placeholder="Search developer by name..."
        leftIcon="search-outline"
        value={query}
        onChangeText={setQuery}
        autoCapitalize="none"
        containerStyle={styles.searchShadow}
      />

      {activeCity && (
        <View style={{flexDirection: 'row', marginBottom: spacing.md}}>
          <Chip
            label={`City: ${activeCity}`}
            active
            icon="close"
            onPress={() => setManualCity(null)}
          />
        </View>
      )}

      <View style={{marginBottom: spacing.sm}}>
        <AppText variant="h3">
          All Developers{' '}
          <AppText variant="caption" color={colors.textMuted}>
            · {list.total} Found
          </AppText>
        </AppText>
      </View>

      <PaginatedList
        renderSkeleton={() => <DeveloperCardSkeleton />}
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyIcon="business-outline"
        emptyTitle="No developers found"
        emptyMessage="Try a different search term or clear the city filter."
        renderItem={({item}) => (
          <DeveloperCard developer={item} onPress={() => goToDeveloper(item.id)} />
        )}
      />

      <LocationPickerSheet
        visible={isPickerVisible}
        onClose={() => setIsPickerVisible(false)}
        cities={['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman']}
        selectedCity={activeCity}
        isDetecting={location.isLoading}
        onUseCurrentLocation={() => {
          setManualCity(null);
          location.detectLocation();
          setIsPickerVisible(false);
        }}
        onSelectCity={city => {
          setManualCity(city);
          setIsPickerVisible(false);
        }}
      />
    </ScreenContainer>
  );
};

const styles = StyleSheet.create({
  // Input now carries its own resting/focused shadow by default — this screen's
  // search bar only needs to opt out of the default border on top of that.
  searchShadow: {
    borderWidth: 0,
  },
});

export default HomeScreen;
