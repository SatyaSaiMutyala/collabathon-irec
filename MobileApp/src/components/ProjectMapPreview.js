import React, {useRef} from 'react';
import {TouchableOpacity, View} from 'react-native';
import MapView, {Marker} from 'react-native-maps';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import {openLink} from '../utils/openLink';
import AppText from './AppText';

const HEIGHT = moderateScale(260);
// Close enough to read street names and the surrounding neighbourhood, matching how
// MapPickerScreen frames a dropped pin (its own PIN_DELTA) rather than the whole city.
const DELTA = {latitudeDelta: 0.03, longitudeDelta: 0.03};

/**
 * A read-only map centred on the project's own coordinates — MapPickerScreen already
 * proved out `react-native-maps` in this app for picking a point; this is the same
 * MapView/Marker pair with no drag/search, just a fixed pin and a recentre button for
 * whoever pans away, plus "Open in Maps" for the full app.
 */
const ProjectMapPreview = ({latitude, longitude, mapsLink}) => {
  const {colors, radius, roundedRadius, spacing} = useAppTheme();
  const mapRef = useRef(null);

  if (latitude == null || longitude == null) {
    return null;
  }

  const region = {latitude, longitude, ...DELTA};
  const recenter = () => mapRef.current?.animateToRegion(region, 300);
  const openInMaps = () =>
    openLink(mapsLink || `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`);

  return (
    <View style={{height: HEIGHT, borderRadius: radius.lg, overflow: 'hidden'}}>
      <MapView
        ref={mapRef}
        style={{flex: 1}}
        initialRegion={region}
        pointerEvents="auto">
        <Marker coordinate={{latitude, longitude}} />
      </MapView>

      <TouchableOpacity
        activeOpacity={0.85}
        onPress={recenter}
        style={{
          position: 'absolute',
          top: spacing.sm,
          right: spacing.sm,
          width: moderateScale(36),
          height: moderateScale(36),
          borderRadius: roundedRadius.control,
          backgroundColor: colors.card,
          alignItems: 'center',
          justifyContent: 'center',
          shadowColor: '#000',
          shadowOffset: {width: 0, height: 2},
          shadowOpacity: 0.15,
          shadowRadius: 4,
          elevation: 3,
        }}>
        <Icon name="navigate" size={moderateScale(17)} color={colors.primary} />
      </TouchableOpacity>

      <TouchableOpacity
        activeOpacity={0.85}
        onPress={openInMaps}
        style={{
          position: 'absolute',
          right: spacing.sm,
          bottom: spacing.sm,
          flexDirection: 'row',
          alignItems: 'center',
          backgroundColor: colors.card,
          borderRadius: roundedRadius.badge,
          paddingHorizontal: spacing.sm,
          paddingVertical: spacing.xs,
          shadowColor: '#000',
          shadowOffset: {width: 0, height: 2},
          shadowOpacity: 0.15,
          shadowRadius: 4,
          elevation: 3,
        }}>
        <Icon name="map" size={moderateScale(15)} color={colors.primary} />
        <AppText variant="captionMedium" color={colors.primary} style={{marginLeft: moderateScale(4)}}>
          Open in Maps
        </AppText>
      </TouchableOpacity>
    </View>
  );
};

export default ProjectMapPreview;
