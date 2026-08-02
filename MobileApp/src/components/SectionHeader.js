import React from 'react';
import {View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const SectionHeader = ({step, title}) => {
  const {colors, spacing, roundedRadius} = useAppTheme();

  return (
    <View style={{flexDirection: 'row', alignItems: 'center'}}>
      {/* A numbered marker, not a surface — it sits beside the heading rather than
          containing anything, so it takes the rounded exception like the other markers. */}
      <View
        style={{
          width: moderateScale(26),
          height: moderateScale(26),
          borderRadius: roundedRadius.step,
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
