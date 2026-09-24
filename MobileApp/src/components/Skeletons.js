import React from 'react';
import {View} from 'react-native';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import Card from './Card';
import Skeleton from './Skeleton';

/**
 * Skeletons shaped like the components they stand in for.
 *
 * The point of a skeleton is that nothing moves when the data lands — if the placeholder
 * is a different height or a different number of lines than the real card, the screen
 * jumps and the skeleton has made things worse than a spinner would have. So each one
 * here mirrors its counterpart's actual layout: same avatar size, same line count, same
 * dividers, same Card padding. When a card changes, its skeleton has to change with it.
 *
 * Widths are percentages so a skeleton fills whatever column it is dropped into.
 */

const LINE = moderateScale(11);
const LINE_SM = moderateScale(9);

/** Repeats any skeleton `count` times — every list uses this rather than its own loop. */
export const SkeletonList = ({count = 5, renderItem}) => (
  <>
    {Array.from({length: count}, (_, index) => (
      <React.Fragment key={index}>{renderItem(index)}</React.Fragment>
    ))}
  </>
);

/** Mirrors PropertyCard: one 240pt image block, everything else is overlaid on it. */
export const PropertyCardSkeleton = () => {
  const {spacing} = useAppTheme();
  return <Skeleton height={moderateScale(240)} style={{marginBottom: spacing.md}} />;
};

/**
 * Mirrors ProjectPreviewCard: a 180pt image block, then name/location/category as
 * separate lines below it rather than overlaid — that card has no gradient to hide the
 * text behind, so the skeleton needs its own line shapes instead of one flat block.
 */
export const ProjectPreviewCardSkeleton = () => {
  const {spacing} = useAppTheme();
  return (
    <View style={{marginBottom: spacing.lg}}>
      <Skeleton height={moderateScale(180)} />
      <Skeleton width="60%" height={LINE} style={{marginTop: spacing.sm}} />
      <Skeleton width="40%" height={LINE_SM} style={{marginTop: moderateScale(6)}} />
      <Skeleton width="35%" height={LINE_SM} style={{marginTop: moderateScale(6)}} />
    </View>
  );
};

/**
 * Mirrors a row on the developer's My Projects list: a PropertyCard with the
 * acceptance-status strip underneath. The strip is part of the row's height, so a bare
 * PropertyCardSkeleton is ~28pt short per row — over five rows that is enough of a jump
 * on first load to look like the screen scrolling by itself.
 */
export const MyProjectCardSkeleton = () => {
  const {spacing} = useAppTheme();
  return (
    <View>
      <PropertyCardSkeleton />
      <View
        style={[
          styles.row,
          {marginTop: -spacing.xs, marginBottom: spacing.sm},
        ]}>
        <Skeleton width={moderateScale(120)} height={moderateScale(22)} />
        <Skeleton
          width={moderateScale(72)}
          height={LINE_SM}
          style={{marginLeft: spacing.xs}}
        />
      </View>
    </View>
  );
};

/** Mirrors PartnerCard: md avatar + two lines + a right-aligned count, then three rows. */
export const PartnerCardSkeleton = () => {
  const {colors, spacing, avatarSize} = useAppTheme();
  return (
    <Card style={{marginBottom: spacing.sm}}>
      <View style={styles.row}>
        <Skeleton
          width={avatarSize.md}
          height={avatarSize.md}
          radius={moderateScale(999)}
        />
        <View style={{flex: 1, marginLeft: spacing.sm}}>
          <Skeleton width="60%" height={LINE} />
          <Skeleton width="40%" height={LINE_SM} style={{marginTop: moderateScale(6)}} />
        </View>
        <View style={{alignItems: 'flex-end'}}>
          <Skeleton width={moderateScale(26)} height={moderateScale(16)} />
          <Skeleton
            width={moderateScale(38)}
            height={LINE_SM}
            style={{marginTop: moderateScale(5)}}
          />
        </View>
      </View>
      <View style={[styles.divider, {borderTopColor: colors.border, marginTop: spacing.sm, paddingTop: spacing.sm}]}>
        <Skeleton width="55%" height={LINE_SM} />
        <Skeleton width="70%" height={LINE_SM} style={{marginTop: moderateScale(7)}} />
        <Skeleton width="45%" height={LINE_SM} style={{marginTop: moderateScale(7)}} />
      </View>
    </Card>
  );
};

/** Mirrors BrokerLeadCard: avatar + two lines + badge, then the contact rows. */
export const BrokerLeadCardSkeleton = () => {
  const {colors, spacing, avatarSize} = useAppTheme();
  return (
    <Card style={{marginBottom: spacing.sm}}>
      <View style={styles.row}>
        <Skeleton width={avatarSize.md} height={avatarSize.md} radius={moderateScale(999)} />
        <View style={{flex: 1, marginLeft: spacing.sm}}>
          <Skeleton width="55%" height={LINE} />
          <Skeleton width="35%" height={LINE_SM} style={{marginTop: moderateScale(6)}} />
        </View>
        <Skeleton width={moderateScale(64)} height={moderateScale(20)} radius={moderateScale(999)} />
      </View>
      <View style={[styles.divider, {borderTopColor: colors.border, marginTop: spacing.sm, paddingTop: spacing.sm}]}>
        <Skeleton width="50%" height={LINE_SM} />
        <Skeleton width="65%" height={LINE_SM} style={{marginTop: moderateScale(7)}} />
        <Skeleton width="40%" height={LINE_SM} style={{marginTop: moderateScale(7)}} />
      </View>
    </Card>
  );
};

/** Mirrors LeadCard: cover photo, title with a badge beside it, location, price, the
 * developer row, then the "View request" button. */
export const LeadCardSkeleton = () => {
  const {colors, spacing, avatarSize} = useAppTheme();
  return (
    <View style={[styles.leadCard, {backgroundColor: colors.card}]}>
      <Skeleton height={moderateScale(160)} />
      <View style={{padding: spacing.md}}>
        <View style={[styles.row, {alignItems: 'flex-start'}]}>
          <View style={{flex: 1}}>
            <Skeleton width="72%" height={moderateScale(16)} />
            <Skeleton width="44%" height={LINE_SM} style={{marginTop: moderateScale(7)}} />
          </View>
          <Skeleton width={moderateScale(64)} height={moderateScale(20)} radius={moderateScale(999)} />
        </View>
        <Skeleton width="40%" height={LINE} style={{marginTop: spacing.sm}} />
        <View
          style={[
            styles.row,
            {
              borderTopWidth: 1,
              borderTopColor: colors.border,
              paddingTop: spacing.sm,
              marginTop: spacing.md,
            },
          ]}>
          <Skeleton width={avatarSize.sm} height={avatarSize.sm} />
          <View style={{flex: 1, marginLeft: moderateScale(10)}}>
            <Skeleton width="55%" height={LINE} />
          </View>
          <Skeleton width={moderateScale(70)} height={LINE_SM} />
        </View>
        <Skeleton height={moderateScale(44)} style={{marginTop: spacing.md}} />
      </View>
    </View>
  );
};

/** Mirrors DeveloperCard: the full card is just the logo, nothing below it. */
export const DeveloperCardSkeleton = () => {
  const {spacing} = useAppTheme();
  return (
    <Card padded={false} style={{marginBottom: spacing.md, overflow: 'hidden'}}>
      <Skeleton width="100%" height={moderateScale(150)} radius={0} />
    </Card>
  );
};

/** Mirrors ProjectMiniCard: 46pt thumb + two lines, inside a bordered row. */
export const ProjectMiniCardSkeleton = () => {
  const {colors} = useAppTheme();
  return (
    <View style={[styles.miniCard, {borderColor: colors.border, backgroundColor: colors.card}]}>
      <Skeleton width={moderateScale(46)} height={moderateScale(46)} />
      <View style={{flex: 1, marginLeft: moderateScale(10)}}>
        <Skeleton width="60%" height={LINE} />
        <Skeleton width="40%" height={LINE_SM} style={{marginTop: moderateScale(6)}} />
      </View>
    </View>
  );
};

/** Mirrors a NotificationsScreen row: 38pt disc + title/meta + body line. */
export const NotificationRowSkeleton = () => {
  const {colors, spacing} = useAppTheme();
  return (
    <View
      style={{
        flexDirection: 'row',
        paddingVertical: spacing.md,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
      }}>
      {/* Must track the real disc in NotificationsScreen, or the row shifts on load. */}
      <Skeleton
        width={moderateScale(30)}
        height={moderateScale(30)}
        radius={moderateScale(999)}
      />
      <View style={{flex: 1, marginLeft: spacing.sm}}>
        <Skeleton width="70%" height={LINE} />
        <Skeleton width="90%" height={LINE_SM} style={{marginTop: moderateScale(7)}} />
      </View>
    </View>
  );
};

/** Mirrors a labelled InfoRow: icon + caption + value. */
export const InfoRowSkeleton = ({labelWidth = '30%', valueWidth = '55%'}) => {
  const {spacing} = useAppTheme();
  return (
    <View style={{flexDirection: 'row', alignItems: 'flex-start', paddingVertical: spacing.sm}}>
      <Skeleton width={moderateScale(18)} height={moderateScale(18)} />
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <Skeleton width={labelWidth} height={LINE_SM} />
        <Skeleton width={valueWidth} height={LINE} style={{marginTop: moderateScale(6)}} />
      </View>
    </View>
  );
};

/** A titled card of InfoRows — the shape every detail section on this app uses. */
export const DetailSectionSkeleton = ({rows = 4}) => {
  const {spacing} = useAppTheme();
  return (
    <>
      <Skeleton width="28%" height={LINE_SM} style={{marginTop: spacing.lg}} />
      <Card style={{marginTop: spacing.xs}}>
        <SkeletonList count={rows} renderItem={() => <InfoRowSkeleton />} />
      </Card>
    </>
  );
};

/** Mirrors BrokerDetailScreen / ProfileScreen: centred identity block, then sections. */
export const ProfileDetailSkeleton = ({sections = 2}) => {
  const {spacing, avatarSize} = useAppTheme();
  return (
    <View>
      <View style={{alignItems: 'center', marginTop: spacing.lg}}>
        <Skeleton
          width={avatarSize.xl}
          height={avatarSize.xl}
          radius={moderateScale(999)}
        />
        <Skeleton width="50%" height={moderateScale(18)} style={{marginTop: spacing.md}} />
        <Skeleton width="35%" height={LINE_SM} style={{marginTop: moderateScale(8)}} />
      </View>
      <SkeletonList count={sections} renderItem={() => <DetailSectionSkeleton />} />
    </View>
  );
};

/**
 * Shown for the whole screen while a project loads (ProjectDetailScreen/
 * PropertyLeadsScreen) — no real header or tab strip has rendered yet at that point,
 * so this draws its own placeholder versions of both, then Overview's own shape: hero
 * photo, price line, and the configuration/possession tile pair, followed by a
 * generic section skeleton for whatever loads in underneath. Matching the real shell
 * matters here specifically because this component warned against a mismatch before —
 * a skeleton for a layout that no longer renders is a guaranteed jump the moment the
 * data lands.
 */
export const PropertyDetailSkeleton = () => {
  const {spacing} = useAppTheme();
  return (
    <View style={{paddingTop: moderateScale(50)}}>
      <View style={[styles.row, {paddingHorizontal: spacing.lg, marginBottom: spacing.sm}]}>
        <Skeleton width={moderateScale(24)} height={moderateScale(24)} radius={moderateScale(999)} />
        <Skeleton width="55%" height={moderateScale(20)} style={{marginLeft: spacing.sm}} />
      </View>
      <View style={[styles.row, {paddingHorizontal: spacing.lg, marginBottom: spacing.md}]}>
        {[0, 1, 2, 3].map(index => (
          <Skeleton
            key={index}
            width="22%"
            height={LINE_SM}
            style={{marginRight: index < 3 ? spacing.sm : 0}}
          />
        ))}
      </View>
      <View style={{paddingHorizontal: spacing.lg}}>
        <Skeleton height={moderateScale(220)} radius={moderateScale(14)} />
        <Skeleton width="70%" height={moderateScale(20)} style={{marginTop: spacing.lg}} />
        <Skeleton width="45%" height={LINE_SM} style={{marginTop: moderateScale(8)}} />
        <View style={[styles.row, {marginTop: spacing.md}]}>
          <Skeleton width="48%" height={moderateScale(56)} />
          <Skeleton width="48%" height={moderateScale(56)} style={{marginLeft: '4%'}} />
        </View>
        <DetailSectionSkeleton rows={4} />
      </View>
    </View>
  );
};

/** Mirrors DashboardScreen: greeting row, stat card, chart card, then a listing. */
export const DashboardSkeleton = () => {
  const {spacing, avatarSize} = useAppTheme();
  return (
    <View>
      <View style={[styles.row, {marginTop: spacing.sm}]}>
        <Skeleton
          width={avatarSize.sm}
          height={avatarSize.sm}
          radius={moderateScale(999)}
        />
        <View style={{flex: 1, marginLeft: spacing.sm}}>
          <Skeleton width="30%" height={LINE_SM} />
          <Skeleton width="55%" height={moderateScale(16)} style={{marginTop: moderateScale(6)}} />
        </View>
        <Skeleton
          width={moderateScale(44)}
          height={moderateScale(44)}
          radius={moderateScale(999)}
        />
      </View>

      <Card style={{marginTop: spacing.lg}}>
        <View style={styles.row}>
          {[0, 1, 2].map(index => (
            <View key={index} style={{flex: 1, alignItems: 'center'}}>
              <Skeleton width="50%" height={moderateScale(18)} />
              <Skeleton width="75%" height={LINE_SM} style={{marginTop: moderateScale(6)}} />
            </View>
          ))}
        </View>
      </Card>

      <Skeleton width="40%" height={moderateScale(15)} style={{marginTop: spacing.xl}} />
      <Card style={{marginTop: spacing.sm}}>
        <Skeleton height={moderateScale(180)} />
      </Card>

      <Skeleton width="35%" height={moderateScale(15)} style={{marginTop: spacing.xl}} />
      <View style={{marginTop: spacing.sm}}>
        <ProjectPreviewCardSkeleton />
      </View>
    </View>
  );
};

const styles = {
  row: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  divider: {
    borderTopWidth: 1,
  },
  leadCard: {
    marginBottom: moderateScale(14),
    overflow: 'hidden',
    shadowColor: '#12141C',
    shadowOffset: {width: 0, height: 3},
    shadowOpacity: 0.11,
    shadowRadius: 8,
    elevation: 3,
  },
  miniCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    padding: moderateScale(8),
    marginBottom: moderateScale(8),
  },
};
