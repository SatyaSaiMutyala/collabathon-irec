import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const Checkbox = ({checked, onToggle, label, error}) => {
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
