import React from 'react';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, verticalScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';

export function useTabBarScreenOptions(tabIcons) {
  const {colors, fontFamily} = useAppTheme();

  return ({route}) => ({
    headerShown: false,
    tabBarActiveTintColor: colors.primary,
    tabBarInactiveTintColor: colors.textMuted,
    tabBarLabelStyle: {
      fontFamily: fontFamily.medium,
      fontSize: moderateScale(11),
      marginBottom: verticalScale(4),
    },
    tabBarStyle: {
      height: verticalScale(64),
      paddingTop: verticalScale(8),
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
