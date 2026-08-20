import React, {useCallback, useEffect, useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
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
import {setMapPickerCallback} from '../../utils/mapPickerCallback';
import {version as APP_VERSION} from '../../../package.json';

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

  // Once only — a GPS fix isn't something to redo every time this tab regains
  // focus, and nothing here changes it anyway once the broker's on the screen.
  useEffect(() => {
    location.detectLocation();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Applies the detected city as the active filter the moment it resolves, rather
  // than leaving it sitting in the header as text the broker still has to open the
  // picker and tap "Use current location" to actually act on — the whole point of
  // detecting it automatically on open is that it's already the filter, not just a
  // label. Guarded on `manualCity === null` so it only ever fills in an *unset*
  // filter: it must not stomp on a city the broker picked themselves (manually, or
  // from the map), and must not un-clear one they explicitly cleared via the chip's
  // ✕ — clearing sets `manualCity` back to null too, but `location.city` itself
  // hasn't changed, so this effect (keyed on `location.city` only) doesn't refire.
  useEffect(() => {
    if (location.city && manualCity === null) {
      setManualCity(location.city);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.city]);

  // Refetched on every focus, not just mount — this tab stays mounted for the life
  // of the session (React Navigation doesn't remount a tab on switch), so a
  // mount-only fetch never saw a request/association made from somewhere else in
  // the app (mark an interest on a property, come back here) until the whole app
  // restarted. Coming back into focus is exactly the moment those counts might
  // have changed.
  useFocusEffect(
    useCallback(() => {
      dispatch(fetchDashboard());
    }, [dispatch]),
  );

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
              label: 'Interested Projects',
              onPress: () => navigation.navigate('RequestsTab'),
            },
            {
              value: String(stats?.associations ?? 0),
              label: 'Approved Interests',
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

      {/* A quiet corner watermark, not a functional control — pointerEvents="none"
          so it never steals a tap from whatever card happens to scroll underneath
          it (DeveloperCard's own city/project pill sits in this same corner). Reads
          package.json's version directly rather than a hand-maintained constant, so
          it can't drift from what actually gets bumped at release time — kept in
          sync with the native versionName in android/app/build.gradle by hand,
          same as any other release-time bump. */}
      <View pointerEvents="none" style={{position: 'absolute', right: spacing.sm, bottom: spacing.xs}}>
        <AppText variant="overline" color={colors.textMuted}>
          v{APP_VERSION}
        </AppText>
      </View>

      <LocationPickerSheet
        visible={isPickerVisible}
        onClose={() => setIsPickerVisible(false)}
        isDetecting={location.isLoading}
        onUseCurrentLocation={() => {
          setManualCity(null);
          location.detectLocation();
          setIsPickerVisible(false);
        }}
        onChooseFromMap={() => {
          setIsPickerVisible(false);
          setMapPickerCallback(result => {
            setManualCity(result.city);
            location.setManualLocation(result.city);
          });
          navigation.navigate('MapPicker');
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
