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
 */
const InfoRow = ({icon, label, value, valueColor, onPress}) => {
  const {colors, spacing} = useAppTheme();
  if (!value) {
    return null;
  }

  const Wrapper = onPress ? TouchableOpacity : View;

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
