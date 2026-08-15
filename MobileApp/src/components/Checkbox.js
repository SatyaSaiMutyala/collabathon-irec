import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * `linkLabel` + `onLinkPress` carve a tappable span out of the label — e.g. "I agree to
 * terms and condition" where only "terms and condition" opens something. It is nested
 * `Text` inside `Text`, not a second `TouchableOpacity` laid over the row: RN gives a
 * nested `Text`'s own `onPress` first claim on taps that land on it, so it opens the
 * link instead of also toggling the box underneath.
 */
const Checkbox = ({checked, onToggle, label, linkLabel, onLinkPress, error}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <View>
      <TouchableOpacity
        activeOpacity={0.8}
        onPress={onToggle}
        style={{flexDirection: 'row', alignItems: 'flex-start'}}>
        <View
          style={{
            width: moderateScale(20),
            height: moderateScale(20),
            borderRadius: 0,
            borderWidth: 1.5,
            borderColor: checked ? colors.primary : (error ? colors.danger : colors.border),
            backgroundColor: checked ? colors.primary : colors.background,
            alignItems: 'center',
            justifyContent: 'center',
            marginTop: moderateScale(1),
          }}>
          {checked && <Icon name="checkmark" size={moderateScale(13)} color={colors.textInverse} />}
        </View>
        <AppText variant="body" color={colors.textSecondary} style={{marginLeft: spacing.sm, flex: 1}}>
          {label}
          {!!linkLabel && (
            <AppText variant="bodyMedium" color={colors.primary} onPress={onLinkPress}>
              {linkLabel}
            </AppText>
          )}
        </AppText>
      </TouchableOpacity>
      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(4)}}>
          {error}
        </AppText>
      )}
    </View>
  );
};

export default Checkbox;
