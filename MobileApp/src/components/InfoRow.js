import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const InfoRow = ({icon, label, value, valueColor}) => {
  const {colors, spacing} = useAppTheme();
  if (!value) {
    return null;
  }
  return (
    <View style={{flexDirection: 'row', alignItems: 'flex-start', paddingVertical: spacing.sm}}>
      <Icon name={icon} size={moderateScale(18)} color={colors.primary} style={{marginTop: moderateScale(2)}} />
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium" color={valueColor}>
          {value}
        </AppText>
      </View>
    </View>
  );
};

export default InfoRow;
