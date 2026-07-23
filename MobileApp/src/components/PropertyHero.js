import React from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import SwipeableImages from './SwipeableImages';

const HERO_HEIGHT = moderateScale(420);

const PropertyHero = ({project, onBack}) => {
  const {colors, spacing, radius} = useAppTheme();

  return (
    <View style={styles.heroWrap}>
      <SwipeableImages
        images={project.images?.length ? project.images : [project.coverImage]}
        height={HERO_HEIGHT}
        dotsPosition="bottom"
      />
      <View pointerEvents="box-none" style={StyleSheet.absoluteFillObject}>
        <LinearGradient
          pointerEvents="none"
          colors={['rgba(0,0,0,0.35)', 'transparent', colors.overlayStrong]}
          style={StyleSheet.absoluteFillObject}
        />
        <TouchableOpacity
          onPress={onBack}
          style={[styles.backButton, {top: moderateScale(50), backgroundColor: colors.overlaySoft}]}>
          <Icon name="chevron-back" size={moderateScale(22)} color={colors.white} />
        </TouchableOpacity>

        <View pointerEvents="none" style={styles.heroBottom}>
          <View style={{flexDirection: 'row'}}>
            <View style={[styles.pill, {backgroundColor: colors.primary, borderRadius: radius.pill}]}>
              <AppText variant="captionMedium" color={colors.textInverse}>
                {project.listingType.toUpperCase()}
              </AppText>
            </View>
          </View>
          <AppText variant="h2" color={colors.textInverse} style={{marginTop: spacing.sm}}>
            {project.name}
          </AppText>
          <AppText variant="body" color={colors.textInverse} style={{marginTop: moderateScale(2)}}>
            📍 {project.location}
          </AppText>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  heroWrap: {
    height: HERO_HEIGHT,
  },
  backButton: {
    position: 'absolute',
    left: moderateScale(16),
    width: moderateScale(40),
    height: moderateScale(40),
    borderRadius: moderateScale(20),
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroBottom: {
    position: 'absolute',
    left: moderateScale(20),
    right: moderateScale(20),
    bottom: moderateScale(36),
  },
  pill: {
    paddingHorizontal: moderateScale(12),
    paddingVertical: moderateScale(6),
  },
});

export default PropertyHero;
