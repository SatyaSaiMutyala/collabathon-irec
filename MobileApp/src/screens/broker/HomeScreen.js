import React, {useCallback, useEffect, useState} from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {firstName} from '../../utils/name';
import {
  AppText,
  Avatar,
  DeveloperCard,
  IconButton,
  Input,
  LocationPickerSheet,
  PaginatedList,
  DeveloperCardSkeleton,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchDevelopers, fetchNextDevelopers} from '../../store/slices/developersSlice';
// Same /dashboard endpoint the developer side's board reads — DashboardController
// branches on the signed-in role, so this broker token gets {requests_sent,
// associations} instead of the developer's listing/lead breakdown.
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

  const [query, setQuery] = useState('');
  const [isPickerVisible, setIsPickerVisible] = useState(false);
  /**
   * Which of the three location choices is in force.
   *
   * 'current' follows the GPS fix and keeps following it across re-detections,
   * 'city' pins whatever was chosen from the map, 'all' applies no filter.
   *
   * A mode rather than a nullable city, because "nothing chosen yet", "the broker
   * cleared it" and "the broker asked to re-detect" are three different intentions
   * that all produced the same `null`. The screen could not tell them apart: tapping
   * Current location cleared the filter and left it to an effect to re-apply the
   * detected city, but that effect was keyed on the city *changing* — so a fix that
   * came back as the same city as before never re-ran it, and the header sat on
   * Hyderabad while the list showed every city.
   */
  const [mode, setMode] = useState('current');
  /** Only meaningful in `city` mode — what the map screen handed back. */
  const [pickedCity, setPickedCity] = useState(null);
  const location = useCurrentLocation();

  // One request per typing pause, not one per keystroke.
  const debouncedQuery = useDebouncedValue(query, 400);
  const activeCity =
    mode === 'all' ? null : mode === 'city' ? pickedCity : (location.city ?? null);

  // Once only — a GPS fix isn't something to redo every time this tab regains
  // focus, and nothing here changes it anyway once the broker's on the screen.
  useEffect(() => {
    location.detectLocation();
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
                {mode === 'all'
                  ? 'All locations'
                  : mode === 'city'
                    ? pickedCity
                    : location.isLoading
                      ? 'Locating…'
                      : (location.city ?? location.label)}
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

      <Input
        placeholder="Search developer by name..."
        leftIcon="search-outline"
        value={query}
        onChangeText={setQuery}
        autoCapitalize="none"
        containerStyle={styles.searchShadow}
      />

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
          setMode('current');
          location.detectLocation();
          setIsPickerVisible(false);
        }}
        onShowAllLocations={() => {
          setMode('all');
          setIsPickerVisible(false);
        }}
        onChooseFromMap={() => {
          setIsPickerVisible(false);
          setMapPickerCallback(result => {
            setMode('city');
            setPickedCity(result.city);
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
