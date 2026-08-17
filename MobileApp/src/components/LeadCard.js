import React from 'react';
import {Image, StyleSheet, TouchableOpacity, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Avatar from './Avatar';

const STATUS = {
  interested: {label: 'AWAITING RESPONSE', tone: 'warning'},
  accepted: {label: 'ACCEPTED', tone: 'success'},
  declined: {label: 'DECLINED', tone: 'danger'},
  viewed: {label: 'VIEWED', tone: 'muted'},
};

const COVER_HEIGHT = moderateScale(164);

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
 * "AED 480,000 Onwards", or nothing. Starting price only, same as PropertyCard and
 * the detail page — intake only ever collects `price_min` now, and a legacy record's
 * stored `price_max` is not shown here either, so every card in the app reads the
 * same regardless of how old the listing is.
 */
const formatPrice = price => {
  if (!price?.min) {
    return null;
  }
  const currency = price.currency || 'AED';
  const money = value => new Intl.NumberFormat('en-IN').format(value);
  return `${currency} ${money(price.min)} Onwards`;
};

/**
 * One of the broker's own leads — a project they asked about, and where that ask got to.
 *
 * Square by construction, not by a radius token that happens to be 0. What gives it
 * weight instead of corners:
 *
 *  - two bands, not one flat field — the developer sits on a tinted footer that runs
 *    edge to edge, which is what stops the lower half looking like leftover space;
 *  - no outline at all — a tight, low shadow holds the edge on its own, which reads
 *    cleaner than a hairline and a shadow doing the same job twice;
 *  - price at display weight, the one number a channel partner actually scans for.
 *
 * The cover band only renders when there is an image. A listing without one gets a
 * shorter, type-led card, which reads as deliberate; a 164pt grey rectangle does not.
 */
const LeadCard = ({lead, onPress, footer}) => {
  const {colors, spacing} = useAppTheme();

  const property = lead.property;
  const developer = lead.developer;

  // PropertyResource nests these under `location`; reading them off the root silently
  // produced an empty string, which hid the row rather than failing loudly.
  const location = [property?.location?.city, property?.location?.locality]
    .filter(Boolean)
    .join(', ');

  const meta = [property?.project_type, property?.project_status].filter(Boolean).join('  ·  ');
  const price = formatPrice(property?.price);

  const status = STATUS[lead.status] ?? {label: String(lead.status).toUpperCase(), tone: 'muted'};
  const statusColor = {
    warning: colors.warning,
    success: colors.success,
    danger: colors.danger,
    muted: colors.textMuted,
  }[status.tone];

  const decidedOn = formatDate(lead.responded_at ?? lead.interested_at);
  const hasCover = !!property?.cover_image_url;

  return (
    <TouchableOpacity
      activeOpacity={0.92}
      onPress={onPress}
      style={[styles.card, {backgroundColor: colors.card}]}>

      <View>
        {hasCover && (
          <View style={[styles.cover, {backgroundColor: colors.surface}]}>
            <Image source={{uri: property.cover_image_url}} style={StyleSheet.absoluteFillObject} />
            {/* Anchors the image to the body below instead of ending on a hard seam. */}
            <LinearGradient
              colors={['transparent', colors.overlaySoft]}
              style={StyleSheet.absoluteFillObject}
            />
          </View>
        )}

        <View style={{paddingHorizontal: spacing.md, paddingTop: spacing.md}}>
          <View style={styles.eyebrow}>
            <AppText variant="overline" color={statusColor} style={styles.eyebrowText}>
              {status.label}
            </AppText>
            {!!location && (
              <AppText
                variant="caption"
                color={colors.textMuted}
                numberOfLines={1}
                style={{marginLeft: 'auto', maxWidth: '55%'}}>
                {location}
              </AppText>
            )}
          </View>

          {/* h3 (17), not h2 (20) — at card width a 20pt project name wrapped to two
              lines on anything but the shortest titles and swamped everything under it. */}
          <AppText variant="h3" numberOfLines={2} style={{marginTop: moderateScale(7)}}>
            {property?.name ?? 'Listing'}
          </AppText>

          {!!meta && (
            <AppText
              variant="caption"
              color={colors.textMuted}
              numberOfLines={1}
              style={{marginTop: moderateScale(4)}}>
              {meta}
            </AppText>
          )}

          {/* bodyMedium (14.5) — the weight, not the size, is what makes the price read
              as the number to scan for. Anything larger competed with the title. */}
          {!!price && (
            <AppText variant="bodyMedium" style={{marginTop: moderateScale(6)}}>
              {price}
            </AppText>
          )}
        </View>

        {/* The tinted block is one section about the developer: who they are, then — when
            a screen passes `footer` — how to reach them. `footer` used to render up in
            the body, which put a phone number and an email above the name they belonged
            to. Anything a caller adds now lands under the identity it describes. */}
        {(!!developer || !!footer) && (
          <View
            style={[
              styles.footerBand,
              {
                backgroundColor: colors.surface,
                borderTopColor: colors.border,
                paddingHorizontal: spacing.md,
                marginTop: spacing.md,
              },
            ]}>
            {!!developer && (
              <View style={styles.identityRow}>
                <Avatar uri={developer.logo_url} name={developer.company_name} size="sm" shape="square" />
                <View style={{flex: 1, marginLeft: moderateScale(10)}}>
                  <AppText variant="bodyMedium" numberOfLines={1}>
                    {developer.company_name}
                  </AppText>
                  {!!decidedOn && (
                    <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
                      {lead.status === 'accepted' ? 'Accepted' : 'Sent'} {decidedOn}
                    </AppText>
                  )}
                </View>
                <Icon name="arrow-forward" size={moderateScale(16)} color={colors.textMuted} />
              </View>
            )}

            {footer}
          </View>
        )}
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
  eyebrow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  eyebrowText: {
    letterSpacing: moderateScale(0.9),
  },
  footerBand: {
    borderTopWidth: 1,
    paddingVertical: moderateScale(11),
  },
  identityRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
};

export default LeadCard;
