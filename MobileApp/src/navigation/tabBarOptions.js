import React from 'react';
import Icon from 'react-native-vector-icons/Ionicons';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import {moderateScale, verticalScale} from '../theme/scaling';
import {useAppTheme} from '../theme';

/**
 * Content height — icon + label + padding — deliberately NOT run through
 * verticalScale. A tab bar's own chrome shouldn't grow just because the phone is
 * taller; extra screen height should go to content, same reasoning `scaling.js`
 * already applies to tablet width. 56, Material Design's own minimum bottom-nav
 * height, not the previous 64 — the icon (22) and label (11 + 4 margin) only need
 * about 45 of that, so 56 is already generous room.
 */
const TAB_BAR_CONTENT_HEIGHT = 56;

export function useTabBarScreenOptions(tabIcons) {
  const {colors, fontFamily, fontWeight} = useAppTheme();
  // targetSdk 35+ (Android 15/16) enforces edge-to-edge, so the tab bar now draws
  // under the system navigation bar instead of the OS reserving space above it —
  // with no inset accounted for, icons and labels were squeezed into whatever room
  // was left. Adding the inset as bottom padding reserves real space for the system
  // bar on Android and iOS alike. Not capped: a 3-button nav bar reports a taller
  // inset than gesture nav does, and that's the real height that needs reserving —
  // capping this to a small fixed value (as this used to do) left the actual system
  // nav bar overlapping the bottom of the tab bar on every 3-button-nav device,
  // which only ever looked fine on gesture nav because gesture nav's own inset
  // already happens to be small.
  const {bottom: bottomInset} = useSafeAreaInsets();

  return ({route}) => ({
    headerShown: false,
    tabBarActiveTintColor: colors.primary,
    tabBarInactiveTintColor: colors.textMuted,
    tabBarLabelStyle: {
      fontFamily: fontFamily.medium,
      fontWeight: fontWeight.medium,
      fontSize: moderateScale(11),
      letterSpacing: 0,
      marginBottom: verticalScale(4),
    },
    tabBarStyle: {
      height: TAB_BAR_CONTENT_HEIGHT + bottomInset,
      // Flat, not scaled — same reasoning as TAB_BAR_CONTENT_HEIGHT above.
      paddingTop: 6,
      paddingBottom: bottomInset,
      backgroundColor: colors.card,
      borderTopColor: colors.border,
    },
    tabBarIcon: ({focused, color}) => (
      <Icon
        name={focused ? tabIcons[route.name].active : tabIcons[route.name].inactive}
        size={moderateScale(22)}
        color={color}
      />
    ),
  });
}
