import React from 'react';
import {View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const SectionHeader = ({step, title}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <View style={{flexDirection: 'row', alignItems: 'center'}}>
      <View
        style={{
          width: moderateScale(26),
          height: moderateScale(26),
          borderRadius: moderateScale(13),
          backgroundColor: colors.primarySoft,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <AppText variant="captionMedium" weight="bold" color={colors.primaryDark}>
          {step}
        </AppText>
      </View>
      <AppText variant="h3" style={{marginLeft: spacing.sm}}>
        {title}
      </AppText>
    </View>
  );
};

export default SectionHeader;
