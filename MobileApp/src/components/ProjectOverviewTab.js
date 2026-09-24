import React from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import LinearGradient from 'react-native-linear-gradient';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Badge from './Badge';
import Card from './Card';
import ExpandableText from './ExpandableText';
import InfoGrid from './InfoGrid';
import SwipeableImages from './SwipeableImages';

const HERO_HEIGHT = moderateScale(220);

// Same status-driven tone convention DeveloperProfileScreen's STATUS_BADGE already
// uses — a lookup, not a ternary chain, so a status added later (there is a fixed
// four-value enum today, see the properties migration) fails visibly instead of
// silently landing in whichever branch happened to be the default.
const STATUS_TONE = {
  'New Launch': 'primary',
  'Under Construction': 'warning',
  'Nearing Completion': 'warning',
  'Ready to Move': 'success',
};

function formatPrice(value) {
  return new Intl.NumberFormat('en-IN').format(value ?? 0);
}

/**
 * Overview: the hero photo, the price and commission, a two-tile configuration/
 * possession summary, and the About copy — the "what is this and is it worth opening
 * further" facts, everything else having moved to its own tab.
 */
const ProjectOverviewTab = ({project, highlightCommission}) => {
  const {colors, spacing} = useAppTheme();
  const navigation = useNavigation();
  const overview = project.details?.overview ?? {};
  const priceSuffix = project.priceUnit?.startsWith('–') ? '' : project.priceUnit;
  const configLabel = (project.unitTypes ?? []).map(u => u.label).filter(Boolean).join(', ');

  return (
    <View style={{paddingHorizontal: spacing.lg, paddingTop: spacing.xs}}>
      <View style={{height: HERO_HEIGHT, borderRadius: moderateScale(14), overflow: 'hidden'}}>
        <SwipeableImages
          images={project.images?.length ? project.images : [project.coverImage]}
          height={HERO_HEIGHT}
          dotsPosition="bottom"
        />
        <View pointerEvents="none" style={StyleSheet.absoluteFillObject}>
          <LinearGradient
            colors={['transparent', 'transparent', colors.overlayStrong]}
            style={StyleSheet.absoluteFillObject}
          />
          {!!STATUS_TONE[project.listingType] && (
            <View style={{position: 'absolute', top: spacing.sm, right: spacing.sm}}>
              <Badge label={project.listingType} tone={STATUS_TONE[project.listingType]} />
            </View>
          )}
          <View style={{position: 'absolute', left: spacing.sm, right: spacing.sm, bottom: spacing.sm}}>
            <AppText variant="h3" color={colors.textInverse} numberOfLines={1}>
              {project.name}
            </AppText>
            {!!project.location && (
              <View style={{flexDirection: 'row', alignItems: 'center', marginTop: moderateScale(2)}}>
                <Icon name="location" size={moderateScale(12)} color={colors.textInverse} />
                <AppText
                  variant="caption"
                  color={colors.textInverse}
                  numberOfLines={1}
                  style={{marginLeft: moderateScale(4)}}>
                  {project.location}
                </AppText>
              </View>
            )}
          </View>
        </View>
      </View>

      <View style={{marginTop: spacing.lg}}>
        <AppText variant="overline" color={colors.textMuted}>
          {project.currency ?? 'INR'}
        </AppText>
        <View style={{flexDirection: 'row', alignItems: 'flex-end', flexWrap: 'wrap'}}>
          <AppText variant="h1" color={colors.primaryDark}>
            {formatPrice(project.price)}
          </AppText>
          {!!priceSuffix && (
            <AppText
              variant="bodyMedium"
              color={colors.textSecondary}
              style={{marginLeft: moderateScale(4), marginBottom: moderateScale(2)}}>
              {priceSuffix}
            </AppText>
          )}
        </View>
        {/* Plain, not the coloured/iconed emphasis this used to get for a broker's own
            view — commission here is one more fact about the project, not the headline
            reason to be on the page, now that the page itself opens with the photo and
            price doing that job. */}
        {highlightCommission && !!project.commissionPercent && (
          <AppText variant="body" color={colors.textMuted} style={{marginTop: moderateScale(2)}}>
            {project.commissionPercent}% CP commission
          </AppText>
        )}
      </View>

      {(!!configLabel || !!overview.possessionDate) && (
        <View style={{marginTop: spacing.lg}}>
          <InfoGrid
            items={[
              {icon: 'bed-outline', label: 'Configuration', value: configLabel},
              {icon: 'calendar-outline', label: 'Possession', value: overview.possessionDate},
            ]}
          />
        </View>
      )}

      {!!project.hasTerms && !!project.terms && (
        <TouchableOpacity
          activeOpacity={0.85}
          onPress={() =>
            navigation.navigate('ProjectTerms', {terms: project.terms, projectName: project.name})
          }
          style={{marginTop: spacing.lg}}>
          <Card style={{flexDirection: 'row', alignItems: 'center'}}>
            <Icon
              name={project.terms.type === 'document' ? 'document-text-outline' : 'reader-outline'}
              size={moderateScale(19)}
              color={colors.primaryDark}
            />
            <View style={{flex: 1, marginLeft: spacing.sm}}>
              <AppText variant="bodyMedium" numberOfLines={1}>
                {project.terms.title}
              </AppText>
              <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
                {project.terms.type === 'document'
                  ? `${(project.terms.documentExtension ?? 'file').toUpperCase()} · tap to read or download`
                  : project.terms.excerpt || 'Tap to read the full terms'}
              </AppText>
            </View>
            <Icon name="chevron-forward" size={moderateScale(18)} color={colors.textMuted} />
          </Card>
        </TouchableOpacity>
      )}

      {!!project.description && (
        <View style={{marginTop: spacing.lg}}>
          <AppText variant="h3" style={{marginBottom: spacing.sm}}>
            About
          </AppText>
          <ExpandableText numberOfLines={4}>{project.description}</ExpandableText>
        </View>
      )}
    </View>
  );
};

export default ProjectOverviewTab;
