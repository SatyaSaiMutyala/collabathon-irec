import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * `stat.onPress` is optional per item — a stat with nothing to drill into (e.g. the
 * "Verified" badge) stays a plain, unpressable View, same as before this existed.
 */
const StatRow = ({stats}) => {
  const {colors, spacing} = useAppTheme();

  const toneColor = tone => {
    if (tone === 'success') {
      return colors.success;
    }
    if (tone === 'muted') {
      return colors.textMuted;
    }
    return colors.primary;
  };

  return (
    <View style={{flexDirection: 'row', alignItems: 'center'}}>
      {stats.map((stat, index) => {
        const Wrapper = stat.onPress ? TouchableOpacity : View;

        return (
          <React.Fragment key={stat.label || index}>
            <Wrapper
              activeOpacity={stat.onPress ? 0.6 : undefined}
              onPress={stat.onPress}
              style={{flex: 1, alignItems: 'center'}}>
              {stat.render ? (
                // Escape hatch for a cell that isn't a plain value+label pair — e.g.
                // a coloured status badge with a year underneath it. Everything
                // else keeps using the plain shape below.
                stat.render()
              ) : (
                <>
                  {stat.icon ? (
                    <Icon name={stat.icon} size={moderateScale(17)} color={toneColor(stat.iconTone)} />
                  ) : (
                    <AppText
                      variant="h3"
                      color={colors.textPrimary}
                      numberOfLines={1}
                      adjustsFontSizeToFit
                      minimumFontScale={0.8}>
                      {stat.value}
                    </AppText>
                  )}
                  {!!stat.label && (
                    <AppText
                      variant="caption"
                      color={colors.textMuted}
                      numberOfLines={1}
                      adjustsFontSizeToFit
                      minimumFontScale={0.8}
                      style={{marginTop: moderateScale(2)}}>
                      {stat.label}
                    </AppText>
                  )}
                </>
              )}
            </Wrapper>
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
        );
      })}
    </View>
  );
};

export default StatRow;
