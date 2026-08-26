import React, {useCallback} from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * Screen title with a back chevron — the header DeveloperProfileScreen and MapPicker
 * already build by hand, extracted so every screen that needs one looks the same.
 *
 * `fallbackRoute` is what makes this usable on a TAB root. A tab screen is not pushed
 * onto a stack, so `goBack()` has nothing to pop when the tab is the navigator's first
 * route or the user landed on it directly — the chevron would render and then do
 * nothing, which is worse than not showing one. When there is no history to pop, this
 * navigates to the given route instead (the tab the user would expect "back" to mean,
 * i.e. Home / Dashboard).
 *
 * `right` is an optional trailing accessory, so a screen that already had one beside
 * its title keeps it.
 */
const BackHeader = ({navigation, title, fallbackRoute, variant = 'h1', right}) => {
  const {colors, spacing} = useAppTheme();

  const handleBack = useCallback(() => {
    if (navigation.canGoBack()) {
      navigation.goBack();
      return;
    }

    if (fallbackRoute) {
      navigation.navigate(fallbackRoute);
    }
  }, [navigation, fallbackRoute]);

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: spacing.sm,
        marginBottom: spacing.lg,
      }}>
      <TouchableOpacity
        onPress={handleBack}
        hitSlop={10}
        accessibilityRole="button"
        accessibilityLabel="Go back">
        <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
      </TouchableOpacity>

      <AppText variant={variant} style={{marginLeft: spacing.sm, flex: 1}}>
        {title}
      </AppText>

      {right}
    </View>
  );
};

export default BackHeader;
