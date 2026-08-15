import React from 'react';
import {Image, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const THUMB = moderateScale(46);

/** "12 Mar 2026" from an ISO string; anything unparseable is dropped. */
const formatDate = iso => {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  return Number.isNaN(date.getTime())
    ? null
    : date.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
};

/**
 * A listing in a supporting list — one line of identity, one of context, and a chevron.
 *
 * Deliberately not PropertyCard: that one is the hero of a browse screen, 240pt tall with
 * a swipeable gallery. Here the project is a detail *about* something else, so it gets a
 * row-height card that a list of a dozen can scroll past without burying the sections
 * underneath it.
 */
const ProjectMiniCard = ({project, meta, onPress}) => {
  const {colors, spacing} = useAppTheme();

  if (!project) {
    return null;
  }

  const location = [project.location?.city, project.location?.state].filter(Boolean).join(', ');
  const Wrapper = onPress ? TouchableOpacity : View;

  return (
    <Wrapper
      {...(onPress ? {activeOpacity: 0.85, onPress} : {})}
      style={[styles.card, {borderColor: colors.border, backgroundColor: colors.card}]}>
      {project.cover_image_url ? (
        <Image source={{uri: project.cover_image_url}} style={styles.thumb} />
      ) : (
        <View style={[styles.thumb, styles.thumbFallback, {backgroundColor: colors.surface}]}>
          <Icon name="business-outline" size={moderateScale(18)} color={colors.textMuted} />
        </View>
      )}

      <View style={{flex: 1, marginLeft: spacing.sm}}>
        <AppText variant="bodyMedium" numberOfLines={1}>
          {project.name ?? 'Untitled listing'}
        </AppText>
        <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
          {[location, project.project_status].filter(Boolean).join(' · ')}
        </AppText>
        {!!meta && (
          <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
            {meta}
          </AppText>
        )}
      </View>

      {!!onPress && (
        <Icon name="chevron-forward" size={moderateScale(16)} color={colors.textMuted} />
      )}
    </Wrapper>
  );
};

ProjectMiniCard.formatDate = formatDate;

const styles = {
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    padding: moderateScale(8),
    marginBottom: moderateScale(8),
  },
  thumb: {
    width: THUMB,
    height: THUMB,
  },
  thumbFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
};

export default ProjectMiniCard;
