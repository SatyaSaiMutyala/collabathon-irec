import React from 'react';
import {Image, StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import {initialsOf} from '../utils/name';
import AppText from './AppText';
import Card from './Card';

const LOGO_HEIGHT = moderateScale(150);

/**
 * Reads the API's developer shape: company_name / logo_url / properties_count.
 * `properties_count` is a server-side aggregate — the card must never rely on a
 * loaded projects array, because a developer's listings are paginated separately.
 *
 * The card is the logo, full-bleed — no name row, no chevron underneath it. `cover`,
 * not `contain`: a letterboxed logo left bars of empty surface down both sides, which
 * read as broken layout rather than intentional whitespace. Location and project
 * count are the only things that ever sit on top of it.
 */
const DeveloperCard = ({developer, onPress}) => {
  const {colors, roundedRadius, spacing} = useAppTheme();

  const count = developer.properties_count ?? 0;
  const projectLabel = `${count} ${count === 1 ? 'Project' : 'Projects'}`;

  return (
    <TouchableOpacity activeOpacity={0.9} onPress={onPress} style={{marginBottom: spacing.md}}>
      <Card padded={false} style={{borderWidth: 0, overflow: 'hidden'}}>
        <View style={[styles.logoWrap, {height: LOGO_HEIGHT}]}>
          {developer.logo_url ? (
            <Image
              source={{uri: developer.logo_url}}
              resizeMode="cover"
              style={StyleSheet.absoluteFillObject}
            />
          ) : (
            <View
              style={[StyleSheet.absoluteFillObject, styles.logoFallback, {backgroundColor: colors.primarySoft}]}>
              <AppText variant="h1" color={colors.primaryDark}>
                {initialsOf(developer.company_name)}
              </AppText>
            </View>
          )}

          <View
            style={[
              styles.cornerPill,
              {backgroundColor: colors.overlayStrong, borderRadius: roundedRadius.badge},
            ]}>
            <Icon name="location-outline" size={moderateScale(11)} color={colors.textInverse} />
            <AppText
              variant="captionMedium"
              color={colors.textInverse}
              numberOfLines={1}
              style={{marginLeft: moderateScale(3), maxWidth: moderateScale(80)}}>
              {developer.city ?? '—'}
            </AppText>
            <View style={[styles.pillDivider, {backgroundColor: colors.textInverse}]} />
            <AppText variant="captionMedium" color={colors.textInverse}>
              {projectLabel}
            </AppText>
          </View>
        </View>
      </Card>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  logoWrap: {
    width: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  cornerPill: {
    position: 'absolute',
    right: moderateScale(8),
    bottom: moderateScale(8),
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: moderateScale(8),
    paddingVertical: moderateScale(4),
  },
  pillDivider: {
    width: 1,
    height: moderateScale(10),
    marginHorizontal: moderateScale(6),
    opacity: 0.5,
  },
});

export default DeveloperCard;
