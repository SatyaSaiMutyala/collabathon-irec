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

  /*
   * 'stretch', not 'center'. With 'center' each cell was sized to its own content and
   * then centred as a block, so two cells of different internal height — a plain
   * value+label pair beside a badge with a year under it — never lined up: the badge
   * floated above the number while its caption sat below the other one. Stretching
   * makes every cell as tall as the tallest, and each cell centres its own content
   * inside that shared height instead.
   */
  return (
    <View style={{flexDirection: 'row', alignItems: 'stretch'}}>
      {stats.map((stat, index) => {
        const Wrapper = stat.onPress ? TouchableOpacity : View;

        return (
          <React.Fragment key={stat.label || index}>
            <Wrapper
              activeOpacity={stat.onPress ? 0.6 : undefined}
              onPress={stat.onPress}
              style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
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
                  // Without this the stretching row would override the fixed height.
                  alignSelf: 'center',
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
