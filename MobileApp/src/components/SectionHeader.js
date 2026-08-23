import React from 'react';
import {View} from 'react-native';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/** `right` is an optional accessory (e.g. a "Start Over" link) — omitted by every
 *  caller that doesn't need one, so this stays a no-op change for them. */
const SectionHeader = ({step, title, right}) => {
  const {colors, spacing, roundedRadius} = useAppTheme();

  return (
    <View style={{flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'}}>
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
      {right}
    </View>
  );
};

export default SectionHeader;
