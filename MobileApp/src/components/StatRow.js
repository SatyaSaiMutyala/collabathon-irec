import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const StatRow = ({stats}) => {
  const {colors, spacing} = useAppTheme();

  const toneColor = tone => {
    if (tone === 'success') return colors.success;
    if (tone === 'muted') return colors.textMuted;
    return colors.primary;
  };

  return (
    <View style={{flexDirection: 'row', alignItems: 'center'}}>
      {stats.map((stat, index) => (
        <React.Fragment key={stat.label}>
          <View style={{flex: 1, alignItems: 'center'}}>
            {stat.icon ? (
              <Icon name={stat.icon} size={moderateScale(17)} color={toneColor(stat.iconTone)} />
            ) : (
              <AppText variant="h3" color={colors.textPrimary}>
                {stat.value}
              </AppText>
            )}
            <AppText
              variant="caption"
              color={colors.textMuted}
              numberOfLines={1}
              adjustsFontSizeToFit
              minimumFontScale={0.8}
              style={{marginTop: moderateScale(2)}}>
              {stat.label}
            </AppText>
          </View>
          {index < stats.length - 1 && (
            <View
              style={{
                width: 1,
                height: moderateScale(22),
                backgroundColor: colors.border,
                marginHorizontal: spacing.xs,
              }}
            />
          )}
        </React.Fragment>
      ))}
    </View>
  );
};

export default StatRow;
