import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {respondToLead} from '../store/slices/leadsSlice';
import AppText from './AppText';
import Avatar from './Avatar';
import Badge from './Badge';
import Button from './Button';
import Card from './Card';

/**
 * A lead as the developer sees it.
 *
 * Contact details are shown when `contact_unlocked` is true — and the server simply
 * does not send mobile/email otherwise, so a locked lead has nothing here to leak.
 */
const BrokerLeadCard = ({lead, propertyName, onPress}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const respondStatus = useAppSelector(state => state.leads.respondStatus);

  const broker = lead.broker;
  if (!broker) {
    return null;
  }

  const unlocked = lead.contact_unlocked;
  const canRespond = lead.status === 'interested';
  const Wrapper = onPress ? TouchableOpacity : View;

  const tone = {accepted: 'success', declined: 'danger', interested: 'warning'}[lead.status];
  const label = {accepted: 'Accepted', declined: 'Rejected', interested: 'Interested'}[lead.status];

  return (
    <Wrapper {...(onPress ? {activeOpacity: 0.85, onPress} : {})}>
      <Card style={{marginBottom: spacing.sm}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          <Avatar name={broker.name} size="md" />
          <View style={{flex: 1, marginLeft: spacing.sm}}>
            <AppText variant="bodyMedium" numberOfLines={1}>
              {broker.name}
            </AppText>
            {!!broker.company_name && (
              <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
                {broker.company_name}
              </AppText>
            )}
            {!!propertyName && (
              <AppText
                variant="caption"
                color={colors.primaryDark}
                numberOfLines={1}
                style={{marginTop: moderateScale(2)}}>
                {propertyName}
              </AppText>
            )}
          </View>
          {!!label && <Badge label={label} tone={tone} />}
        </View>

        {unlocked ? (
          <View
            style={{
              marginTop: spacing.sm,
              paddingTop: spacing.sm,
              borderTopWidth: 1,
              borderTopColor: colors.border,
            }}>
            {!!broker.mobile && (
              <View
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  marginBottom: moderateScale(6),
                }}>
                <Icon name="call-outline" size={moderateScale(14)} color={colors.primary} />
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginLeft: moderateScale(6)}}>
                  {broker.mobile}
                </AppText>
              </View>
            )}
            {!!broker.email && (
              <View
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  marginBottom: moderateScale(6),
                }}>
                <Icon name="mail-outline" size={moderateScale(14)} color={colors.primary} />
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginLeft: moderateScale(6)}}>
                  {broker.email}
                </AppText>
              </View>
            )}
            {!!broker.rera_number && (
              <View style={{flexDirection: 'row', alignItems: 'center'}}>
                <Icon
                  name="shield-checkmark-outline"
                  size={moderateScale(14)}
                  color={colors.primary}
                />
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginLeft: moderateScale(6)}}>
                  {broker.rera_number}
                </AppText>
              </View>
            )}

            {canRespond && (
              <View style={{flexDirection: 'row', marginTop: spacing.md}}>
                <View style={{flex: 1, marginRight: spacing.xs}}>
                  <Button
                    label="Decline"
                    variant="outline"
                    size="md"
                    disabled={respondStatus === 'loading'}
                    onPress={() => dispatch(respondToLead({leadId: lead.id, status: 'declined'}))}
                  />
                </View>
                <View style={{flex: 1, marginLeft: spacing.xs}}>
                  <Button
                    label="Accept"
                    size="md"
                    disabled={respondStatus === 'loading'}
                    onPress={() => dispatch(respondToLead({leadId: lead.id, status: 'accepted'}))}
                  />
                </View>
              </View>
            )}
          </View>
        ) : (
          <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
            <Icon name="lock-closed-outline" size={moderateScale(13)} color={colors.textMuted} />
            <AppText
              variant="caption"
              color={colors.textMuted}
              style={{marginLeft: moderateScale(6)}}>
              Viewed only — contact hidden until this broker marks interest
            </AppText>
          </View>
        )}
      </Card>
    </Wrapper>
  );
};

export default BrokerLeadCard;
