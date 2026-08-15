import React from 'react';
import {Image, StyleSheet, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {roundedRadius, useAppTheme} from '../theme';
import AppText from './AppText';
import {initialsOf} from '../utils/name';

const Avatar = ({uri, name, size = 'md', ringColor, showVerified}) => {
  const {colors, avatarSize} = useAppTheme();
  const dim = avatarSize[size];
  const ringWidth = ringColor ? moderateScale(2) : 0;

  return (
    <View
      style={[
        styles.wrapper,
        {
          width: dim + ringWidth * 2,
          height: dim + ringWidth * 2,
          borderRadius: roundedRadius.avatar,
          borderWidth: ringWidth,
          borderColor: ringColor,
        },
      ]}>
      {uri ? (
        <Image
          source={{uri}}
          style={{width: dim, height: dim, borderRadius: roundedRadius.avatar}}
        />
      ) : (
        <View
          style={[
            styles.fallback,
            {
              width: dim,
              height: dim,
              borderRadius: roundedRadius.avatar,
              backgroundColor: colors.primarySoft,
            },
          ]}>
          <AppText variant="bodyMedium" color={colors.primaryDark}>
            {initialsOf(name)}
          </AppText>
        </View>
      )}
      {showVerified && (
        <View style={[styles.badge, {backgroundColor: colors.success, borderColor: colors.card}]}>
          <Icon name="checkmark" size={moderateScale(9)} color={colors.white} />
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  fallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  badge: {
    position: 'absolute',
    right: -moderateScale(1),
    bottom: -moderateScale(1),
    width: moderateScale(16),
    height: moderateScale(16),
    borderRadius: roundedRadius.badge,
    borderWidth: moderateScale(1.5),
    alignItems: 'center',
    justifyContent: 'center',
  },
});

export default Avatar;
