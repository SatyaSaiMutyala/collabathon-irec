import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Button from './Button';
import RemoteImage from './RemoteImage';

const IMAGE_HEIGHT = moderateScale(180);

/**
 * A project shown as supporting content on someone else's page — a developer's
 * profile, or (with `buttonLabel`) a developer's own dashboard — rather than the hero
 * of its own browse screen. Deliberately not PropertyCard: that one is a 240pt
 * dark-gradient-overlay card built to be the main thing on a listings feed; here the
 * project is one of several facts on the page, so it gets a plain photo with the
 * name/meta/description underneath, closer to how a portfolio piece reads than a
 * listing ad.
 *
 * `showType`/`buttonLabel` are both opt-in so DeveloperProfileScreen's existing rows
 * (no type prefix, no button — the whole row is already tappable there) render exactly
 * as before; only a caller that asks for them gets the type|location meta line and the
 * explicit CTA.
 *
 * `descriptionExcerpt`, not the full `description`: that field is allowed up to 20,000
 * characters and only arrives on a single project's detail fetch, not in this list's
 * payload — the excerpt is a bounded ~160-char teaser sent on every row precisely so a
 * list of many projects stays light. The full write-up is one tap away on the detail
 * page this card already opens.
 */
const ProjectPreviewCard = ({project, onPress, showType = false, buttonLabel}) => {
  const {colors, radius, spacing} = useAppTheme();
  const meta = showType ? [project.type, project.location].filter(Boolean).join('  |  ') : null;

  return (
    <TouchableOpacity activeOpacity={0.85} onPress={onPress} style={{marginBottom: spacing.lg}}>
      <RemoteImage
        uri={project.coverImage}
        style={{width: '100%', height: IMAGE_HEIGHT, borderRadius: radius.lg}}
        resizeMode="cover"
        fallback={
          <View
            style={{
              width: '100%',
              height: IMAGE_HEIGHT,
              borderRadius: radius.lg,
              backgroundColor: colors.surface,
              alignItems: 'center',
              justifyContent: 'center',
            }}>
            <Icon name="business-outline" size={moderateScale(28)} color={colors.textMuted} />
          </View>
        }
      />

      <AppText variant="h3" numberOfLines={1} style={{marginTop: spacing.sm}}>
        {project.name}
      </AppText>

      {showType ? (
        !!meta && (
          <AppText variant="caption" color={colors.textMuted} numberOfLines={1} style={{marginTop: moderateScale(3)}}>
            {meta}
          </AppText>
        )
      ) : (
        !!project.location && (
          <View style={{flexDirection: 'row', alignItems: 'center', marginTop: moderateScale(3)}}>
            <Icon name="location-outline" size={moderateScale(13)} color={colors.textMuted} />
            <AppText
              variant="caption"
              color={colors.textMuted}
              numberOfLines={1}
              style={{marginLeft: moderateScale(4)}}>
              {project.location}
            </AppText>
          </View>
        )
      )}

      {!!project.descriptionExcerpt && (
        <AppText
          variant="body"
          color={colors.textSecondary}
          numberOfLines={2}
          style={{marginTop: moderateScale(4)}}>
          {project.descriptionExcerpt}
        </AppText>
      )}

      {!!buttonLabel && <Button label={buttonLabel} onPress={onPress} style={{marginTop: spacing.md}} />}
    </TouchableOpacity>
  );
};

export default ProjectPreviewCard;
