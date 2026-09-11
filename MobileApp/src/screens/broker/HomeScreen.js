import React, {useCallback, useEffect, useState} from 'react';
import {useFocusEffect} from '@react-navigation/native';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {firstName} from '../../utils/name';
import {
  AppText,
  Avatar,
  Card,
  DeveloperCard,
  IconButton,
  Input,
  LocationPickerSheet,
  PaginatedList,
  StatRow,
  DeveloperCardSkeleton,
  ScreenContainer,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchDevelopers, fetchNextDevelopers} from '../../store/slices/developersSlice';
import {fetchDashboard} from '../../store/slices/dashboardSlice';
// Same /dashboard endpoint the developer side's board reads — DashboardController
// branches on the signed-in role, so this broker token gets {requests_sent,
// associations} instead of the developer's listing/lead breakdown.
import {useCurrentLocation} from '../../hooks/useLocation';
import {useDebouncedValue} from '../../hooks/useDebouncedValue';
import {setMapPickerCallback} from '../../utils/mapPickerCallback';

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
  /**
   * Only meaningful in `city` mode — the whole place the map screen handed back, not
   * just its name. It already resolves a point, and keeping that is what lets choosing
   * a city re-centre the list instead of only re-labelling the header.
   */
  const [pickedPlace, setPickedPlace] = useState(null);
  const location = useCurrentLocation();

  // One request per typing pause, not one per keystroke.
  const debouncedQuery = useDebouncedValue(query, 400);
  /*
   * Where the broker is, sent to the server as a name and a point together, because the
   * two do different jobs. The point orders the list — nearest developer first, then
   * outward, which is what scrolling walks through. The name only tells the server which
   * state to stop at, so the list never wanders into one the broker did not ask for.
   *
   * Either can be missing. No name means no state cap, which is deliberate: a fix that
   * reverse-geocodes to "Kukatpally" used to match no developer at all and left this
   * screen empty. No point just means the list is not ordered by distance.
   */
  const activePlace = mode === 'all' ? null : mode === 'city' ? pickedPlace : location;
  const activeCity = activePlace?.city ?? null;

  // Read out as primitives on purpose. `location` is rebuilt on every render, so a
  // dependency on the object itself would give loadFirstPage a new identity each time
  // and turn the focus effect below into a fetch loop.
  const activeLatitude = activePlace?.latitude ?? null;
  const activeLongitude = activePlace?.longitude ?? null;

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
        // As a pair or not at all: half a coordinate is not a place, and the server
        // discards a lone one anyway rather than guessing the other half.
        ...(activeLatitude !== null && activeLongitude !== null
          ? {lat: activeLatitude, lng: activeLongitude}
          : {}),
      }),
    );
  }, [dispatch, debouncedQuery, activeCity, activeLatitude, activeLongitude]);

  // Any change to search or city restarts at page 1, same as before — but this also
  // now re-runs on every focus, not just on mount. A developer's logo/name/project
  // count can change from the admin panel at any time, and returning to this tab
  // (from a developer's profile, or just switching tabs and back) used to keep
  // showing whatever page 1 looked like the moment this screen first mounted.
  useFocusEffect(
    useCallback(() => {
      loadFirstPage();
      // Refreshed on focus, not just on mount: an interest accepted while this tab
      // was in the background would otherwise leave the counts on the old numbers
      // until the app was restarted.
      dispatch(fetchDashboard());
    }, [loadFirstPage, dispatch]),
  );

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
                    ? pickedPlace?.city
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

      {/* Each cell is a shortcut to the tab that lists what it counts, so the
          number is not a dead end. Figures come from /dashboard — see the note
          on its import above. */}
      <Card style={{paddingVertical: spacing.sm, marginBottom: moderateScale(10)}}>
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

      <View style={{marginBottom: spacing.sm}}>
        <AppText variant="h3">
          All Developers{' '}
          <AppText variant="caption" color={colors.textMuted}>
            · {list.total} Found
          </AppText>
        </AppText>
      </View>

      <PaginatedList
        // Cancels ScreenContainer's own horizontal padding so DeveloperCard's much
        // smaller paddingHorizontal is the only inset left — two stacked paddings
        // (the screen's 20 plus the card's own) were eating enough width that a
        // longer developer name had nowhere left to go before truncating.
        style={{marginHorizontal: -spacing.lg}}
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
            // The map hands back the point it resolved, not just the name — keeping the
            // whole result is what re-centres the list on the chosen city.
            setPickedPlace(result);
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
