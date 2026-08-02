import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Avatar from './Avatar';
import Card from './Card';

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
 * One accepted broker in the partner roster.
 *
 * Every row here is an accepted relationship, so the contact is real — no lock state to
 * render. What the row leads with instead is the thing the list is for: how much work
 * this partner has actually done with you.
 */
const PartnerCard = ({partner, onPress}) => {
  const {colors, spacing} = useAppTheme();

  const projects = partner.projects_count ?? 0;
  const lastSeen = formatDate(partner.last_collaborated_at);
  const location = [partner.city, partner.state].filter(Boolean).join(', ');

  return (
    <TouchableOpacity activeOpacity={0.85} onPress={onPress}>
      <Card style={{marginBottom: spacing.sm}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          <Avatar uri={partner.photo_url} name={partner.name} size="md" />
          <View style={{flex: 1, marginLeft: spacing.sm}}>
            <AppText variant="bodyMedium" numberOfLines={1}>
              {partner.name}
            </AppText>
            {!!partner.company_name && (
              <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
                {partner.company_name}
              </AppText>
            )}
            {!!location && (
              <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
                {location}
              </AppText>
            )}
          </View>
          <View style={{alignItems: 'flex-end'}}>
            <AppText variant="h3" color={colors.primaryDark}>
              {projects}
            </AppText>
            <AppText variant="caption" color={colors.textMuted}>
              {projects === 1 ? 'project' : 'projects'}
            </AppText>
          </View>
        </View>

        <View
          style={{
            marginTop: spacing.sm,
            paddingTop: spacing.sm,
            borderTopWidth: 1,
            borderTopColor: colors.border,
          }}>
          {!!partner.mobile && (
            <View style={styles.row}>
              <Icon name="call-outline" size={moderateScale(14)} color={colors.primary} />
              <AppText
                variant="caption"
                color={colors.textSecondary}
                style={{marginLeft: moderateScale(6)}}>
                {partner.mobile}
              </AppText>
            </View>
          )}
          {!!partner.email && (
            <View style={styles.row}>
              <Icon name="mail-outline" size={moderateScale(14)} color={colors.primary} />
              <AppText
                variant="caption"
                color={colors.textSecondary}
                numberOfLines={1}
                style={{marginLeft: moderateScale(6), flex: 1}}>
                {partner.email}
              </AppText>
            </View>
          )}
          {!!lastSeen && (
            <View style={[styles.row, {marginBottom: 0}]}>
              <Icon name="time-outline" size={moderateScale(14)} color={colors.textMuted} />
              <AppText
                variant="caption"
                color={colors.textMuted}
                style={{marginLeft: moderateScale(6)}}>
                Last accepted {lastSeen}
              </AppText>
            </View>
          )}
        </View>
      </Card>
    </TouchableOpacity>
  );
};

const styles = {
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: moderateScale(6),
  },
};

export default PartnerCard;
