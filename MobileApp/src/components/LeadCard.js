import React from 'react';
import {StyleSheet, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import {formatCompactPrice} from '../utils/money';
import AppText from './AppText';
import Avatar from './Avatar';
import Badge from './Badge';
import Button from './Button';
import RemoteImage from './RemoteImage';

const STATUS = {
  interested: {label: 'Pending', tone: 'warning'},
  accepted: {label: 'Accepted', tone: 'success'},
  declined: {label: 'Declined', tone: 'danger'},
  viewed: {label: 'Viewed', tone: 'neutral'},
};

const COVER_HEIGHT = moderateScale(160);

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
 * One of the broker's own leads — a project they asked about, and where that ask got to.
 *
 * Plain photo on top (no gradient/overlaid text — the reference keeps every fact off
 * the image and in the body below it), a status badge floating beside the name/price
 * instead of an overline on the cover, the developer identity band, and an explicit
 * "View request" button — the same destination `onPress` already opens on the whole
 * card, just given a real, visible action a thumb can aim at instead of only an
 * implicit "tap anywhere" affordance.
 */
const LeadCard = ({lead, onPress, footer, buttonLabel = 'View request'}) => {
  const {colors, spacing} = useAppTheme();

  const property = lead.property;
  const developer = lead.developer;

  // PropertyResource nests these under `location`; reading them off the root silently
  // produced an empty string, which hid the row rather than failing loudly.
  const location = [property?.location?.city, property?.location?.locality]
    .filter(Boolean)
    .join(', ');

  const price = formatCompactPrice(property?.price?.min, property?.price?.currency);
  const status = STATUS[lead.status] ?? {label: String(lead.status), tone: 'neutral'};
  const sentOn = formatDate(lead.interested_at);
  const decidedOn = formatDate(lead.responded_at);
  const hasCover = !!property?.cover_image_url;

  return (
    <TouchableOpacity
      activeOpacity={0.92}
      onPress={onPress}
      style={[styles.card, {backgroundColor: colors.card}]}>
      {hasCover && (
        <View style={[styles.cover, {backgroundColor: colors.surface}]}>
          <RemoteImage
            uri={property.cover_image_url}
            style={StyleSheet.absoluteFillObject}
            fallback={
              <View style={[StyleSheet.absoluteFillObject, styles.coverFallback]}>
                <Icon name="business-outline" size={moderateScale(26)} color={colors.textMuted} />
              </View>
            }
          />
        </View>
      )}

      <View style={{padding: spacing.md}}>
        <View style={{flexDirection: 'row', alignItems: 'flex-start'}}>
          <View style={{flex: 1}}>
            {/* h3 (17), not h2 (20) — at card width a 20pt project name wrapped to two
                lines on anything but the shortest titles and swamped everything under it. */}
            <AppText variant="h3" numberOfLines={2}>
              {property?.name ?? 'Listing'}
            </AppText>
            {!!location && (
              <View style={{flexDirection: 'row', alignItems: 'center', marginTop: moderateScale(3)}}>
                <Icon name="location" size={moderateScale(13)} color={colors.textMuted} />
                <AppText
                  variant="caption"
                  color={colors.textMuted}
                  numberOfLines={1}
                  style={{marginLeft: moderateScale(4)}}>
                  {location}
                </AppText>
              </View>
            )}
          </View>
          <Badge label={status.label} tone={status.tone} />
        </View>

        {/* bodyMedium (14.5) weight, not size, is what makes the price read as the
            number to scan for — anything larger competed with the title. */}
        {!!price && (
          <AppText variant="bodyMedium" weight="semiBold" style={{marginTop: moderateScale(8)}}>
            {price}
          </AppText>
        )}

        {!!developer && (
          <View
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              marginTop: spacing.md,
              paddingTop: spacing.sm,
              borderTopWidth: 1,
              borderTopColor: colors.border,
            }}>
            <Avatar uri={developer.logo_url} name={developer.company_name} size="sm" shape="square" />
            <AppText variant="bodyMedium" numberOfLines={1} style={{flex: 1, marginLeft: moderateScale(10)}}>
              {developer.company_name}
            </AppText>
            {!!(decidedOn || sentOn) && (
              <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
                {lead.status === 'accepted' ? `Accepted ${decidedOn}` : `Sent ${sentOn}`}
              </AppText>
            )}
          </View>
        )}

        {footer}

        <Button
          label={buttonLabel}
          onPress={onPress}
          style={{marginTop: spacing.md}}
        />
      </View>
    </TouchableOpacity>
  );
};

const styles = {
  card: {
    // Deliberately no borderRadius — not even the token, which is 0 today but would
    // round these the day someone changes the scale.
    //
    // No border either: the shadow is what separates the card from the canvas now. It
    // carries a little more weight than it would alongside a hairline, because with the
    // outline gone it is the only thing holding the edge — but it stays tight and low,
    // since a soft glow under a hard-edged card fights the geometry.
    overflow: 'hidden',
    marginBottom: moderateScale(14),
    shadowColor: '#12141C',
    shadowOffset: {width: 0, height: 3},
    shadowOpacity: 0.11,
    shadowRadius: 8,
    elevation: 3,
  },
  cover: {
    height: COVER_HEIGHT,
  },
  coverFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
};

export default LeadCard;
