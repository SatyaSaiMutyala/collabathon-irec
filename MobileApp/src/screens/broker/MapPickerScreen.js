import React, {useEffect, useRef, useState} from 'react';
import {ActivityIndicator, TouchableOpacity, View} from 'react-native';
import MapView, {Marker} from 'react-native-maps';
import Geolocation from '@react-native-community/geolocation';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {AppText, Button, Card, Input, ScreenContainer} from '../../components';
import {useDebouncedValue} from '../../hooks/useDebouncedValue';
import {reverseGeocode, searchPlaces} from '../../utils/geocoding';
import {consumeMapPickerCallback} from '../../utils/mapPickerCallback';

// Dubai — a reasonable centre for this app's own market (see the hardcoded UAE city
// list this screen's sheet already offered) when GPS isn't available yet, so the map
// never opens on the ocean off the coast of Africa (0,0) while a fix is pending.
const DEFAULT_REGION = {
  latitude: 25.2048,
  longitude: 55.2708,
  latitudeDelta: 0.5,
  longitudeDelta: 0.5,
};

const PIN_DELTA = {latitudeDelta: 0.05, longitudeDelta: 0.05};

/**
 * Full-screen "search or tap to pick a point" map, opened from the home screen's
 * location sheet ("Choose from Map"). The result callback comes from
 * `consumeMapPickerCallback()` (see mapPickerCallback.js) rather than a navigation
 * param — React Navigation warns on a function in params ("Non-serializable values
 * were found in the navigation state"), since it can't survive state persistence.
 * Read once on mount: nothing else navigates to this screen in between HomeScreen
 * setting it and this screen mounting.
 */
const MapPickerScreen = ({navigation}) => {
  const {colors, spacing, radius, roundedRadius} = useAppTheme();
  const onSelectRef = useRef(consumeMapPickerCallback());
  const mapRef = useRef(null);

  const [marker, setMarker] = useState(null);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);
  const [isLocating, setIsLocating] = useState(false);

  const debouncedQuery = useDebouncedValue(query, 400);

  // Centre on the device's own position the moment the map opens, if it's available —
  // best-effort only. Permission was already asked for app-wide at launch (see
  // usePrimePermissions), so this never prompts here; it just quietly falls back to
  // DEFAULT_REGION if location is off, denied, or slow to fix.
  useEffect(() => {
    Geolocation.getCurrentPosition(
      position => {
        mapRef.current?.animateToRegion(
          {latitude: position.coords.latitude, longitude: position.coords.longitude, ...PIN_DELTA},
          400,
        );
      },
      () => {},
      {enableHighAccuracy: false, timeout: 8000, maximumAge: 60000},
    );
  }, []);

  useEffect(() => {
    if (!debouncedQuery.trim()) {
      setResults([]);
      return;
    }

    let cancelled = false;
    setIsSearching(true);
    searchPlaces(debouncedQuery)
      .then(found => {
        if (!cancelled) {
          setResults(found);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setResults([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsSearching(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [debouncedQuery]);

  const goToPoint = (latitude, longitude) => {
    setMarker({latitude, longitude});
    mapRef.current?.animateToRegion({latitude, longitude, ...PIN_DELTA}, 400);
  };

  const handleSelectResult = result => {
    goToPoint(result.latitude, result.longitude);
    setQuery(result.city);
    setResults([]);
  };

  const handleLocateMe = () => {
    setIsLocating(true);
    Geolocation.getCurrentPosition(
      position => {
        setIsLocating(false);
        goToPoint(position.coords.latitude, position.coords.longitude);
      },
      () => setIsLocating(false),
      {enableHighAccuracy: true, timeout: 10000, maximumAge: 0},
    );
  };

  const handleConfirm = async () => {
    if (!marker) {
      return;
    }
    setIsConfirming(true);
    try {
      const {city, label} = await reverseGeocode(marker.latitude, marker.longitude);
      onSelectRef.current?.({city, label, latitude: marker.latitude, longitude: marker.longitude});
      navigation.goBack();
    } catch {
      setIsConfirming(false);
    }
  };

  return (
    <ScreenContainer edges={['top']} style={{paddingHorizontal: 0}}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          paddingHorizontal: spacing.lg,
          marginTop: spacing.sm,
          marginBottom: spacing.sm,
        }}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h3" style={{marginLeft: spacing.sm}}>
          Choose location
        </AppText>
      </View>

      <View style={{flex: 1}}>
        <MapView
          ref={mapRef}
          style={{flex: 1}}
          initialRegion={DEFAULT_REGION}
          onPress={e => setMarker(e.nativeEvent.coordinate)}
          // A tap that lands on a labelled point of interest (a shop, a building, a
          // landmark — Google's own map tiles are full of these) fires this instead
          // of onPress and never reaches it at all. Without this, that tap is just
          // silently swallowed — which reads as "the map isn't responding" and only
          // "works" once someone happens to tap empty space instead.
          onPoiClick={e => setMarker(e.nativeEvent.coordinate)}>
          {marker && (
            <Marker
              coordinate={marker}
              draggable
              onDragEnd={e => setMarker(e.nativeEvent.coordinate)}
            />
          )}
        </MapView>

        {/* Search box + results float over the map rather than push it down, so the
            map itself never resizes while typing. */}
        <View style={{position: 'absolute', top: spacing.sm, left: spacing.lg, right: spacing.lg}}>
          <Input
            placeholder="Search a city or area..."
            leftIcon="search-outline"
            value={query}
            onChangeText={setQuery}
            autoCapitalize="none"
            containerStyle={{marginBottom: 0, shadowOpacity: 0.12}}
          />
          {(results.length > 0 || isSearching) && (
            <Card style={{marginTop: spacing.xs, paddingVertical: spacing.xxs}}>
              {isSearching && results.length === 0 ? (
                <View style={{paddingVertical: spacing.sm, alignItems: 'center'}}>
                  <ActivityIndicator size="small" color={colors.primary} />
                </View>
              ) : (
                results.map((result, index) => (
                  <TouchableOpacity
                    key={result.id}
                    activeOpacity={0.7}
                    onPress={() => handleSelectResult(result)}
                    style={{
                      flexDirection: 'row',
                      alignItems: 'flex-start',
                      paddingVertical: spacing.sm,
                      borderTopWidth: index === 0 ? 0 : 1,
                      borderTopColor: colors.border,
                    }}>
                    <Icon
                      name="location-outline"
                      size={moderateScale(16)}
                      color={colors.textSecondary}
                      style={{marginTop: moderateScale(2)}}
                    />
                    <AppText variant="caption" style={{marginLeft: spacing.xs, flex: 1}} numberOfLines={2}>
                      {result.name}
                    </AppText>
                  </TouchableOpacity>
                ))
              )}
            </Card>
          )}
        </View>

        <TouchableOpacity
          activeOpacity={0.85}
          onPress={handleLocateMe}
          style={{
            position: 'absolute',
            right: spacing.lg,
            bottom: spacing.xl * 2.5,
            width: moderateScale(42),
            height: moderateScale(42),
            borderRadius: roundedRadius.control,
            backgroundColor: colors.card,
            alignItems: 'center',
            justifyContent: 'center',
            shadowColor: '#000',
            shadowOffset: {width: 0, height: 2},
            shadowOpacity: 0.15,
            shadowRadius: 6,
            elevation: 4,
          }}>
          {isLocating ? (
            <ActivityIndicator size="small" color={colors.primary} />
          ) : (
            <Icon name="locate" size={moderateScale(20)} color={colors.primary} />
          )}
        </TouchableOpacity>

        {!marker && (
          <View
            pointerEvents="none"
            style={{
              position: 'absolute',
              bottom: spacing.xl * 1.6,
              left: spacing.lg,
              right: spacing.lg,
              alignItems: 'center',
            }}>
            <View
              style={{
                backgroundColor: colors.overlayStrong,
                borderRadius: radius.md,
                paddingHorizontal: spacing.md,
                paddingVertical: spacing.xs,
              }}>
              <AppText variant="caption" color={colors.white}>
                Tap the map to drop a pin
              </AppText>
            </View>
          </View>
        )}
      </View>

      <View style={{padding: spacing.lg}}>
        <Button
          label="Confirm this location"
          onPress={handleConfirm}
          disabled={!marker}
          loading={isConfirming}
        />
      </View>
    </ScreenContainer>
  );
};

export default MapPickerScreen;
