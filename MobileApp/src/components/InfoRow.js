import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * A labelled value. Pass `onPress` for the few rows whose value is a destination rather
 * than a fact — a maps link, a phone number — and the row becomes tappable and grows a
 * chevron so it looks like one. Without it the row stays inert, which is the common case.
 *
 * `compact` puts the label and value on one line instead of stacking them — roughly
 * half the row height, for a screen with many short rows back to back (a contact
 * block, a details list) where the stacked layout's own whitespace is the thing taking
 * up the space, not the text. The value still gets the full remaining row width (no
 * fixed-width tile to wrap awkwardly inside), only the label moves beside it instead
 * of above it — a fixed label column keeps every row's value starting at the same x
 * position, which is what actually reads as a clean list rather than ragged text.
 */
const InfoRow = ({icon, label, value, valueColor, onPress, compact = false}) => {
  const {colors, spacing} = useAppTheme();
  if (!value) {
    return null;
  }

  const Wrapper = onPress ? TouchableOpacity : View;

  if (compact) {
    return (
      <Wrapper
        {...(onPress ? {activeOpacity: 0.7, onPress} : {})}
        style={{flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.xs}}>
        <Icon name={icon} size={moderateScale(16)} color={colors.primary} />
        <AppText
          variant="caption"
          color={colors.textMuted}
          numberOfLines={1}
          style={{marginLeft: spacing.sm, width: moderateScale(104)}}>
          {label}
        </AppText>
        <AppText variant="bodyMedium" color={valueColor} style={{flex: 1, marginLeft: spacing.xs}}>
          {value}
        </AppText>
        {!!onPress && (
          <Icon name="chevron-forward" size={moderateScale(14)} color={colors.textMuted} />
        )}
      </Wrapper>
    );
  }

  return (
    <Wrapper
      {...(onPress ? {activeOpacity: 0.7, onPress} : {})}
      style={{flexDirection: 'row', alignItems: 'flex-start', paddingVertical: spacing.sm}}>
      <Icon name={icon} size={moderateScale(18)} color={colors.primary} style={{marginTop: moderateScale(2)}} />
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium" color={valueColor}>
          {value}
        </AppText>
      </View>
      {!!onPress && (
        <Icon
          name="chevron-forward"
          size={moderateScale(16)}
          color={colors.textMuted}
          style={{marginTop: moderateScale(4)}}
        />
      )}
    </Wrapper>
  );
};

export default InfoRow;
