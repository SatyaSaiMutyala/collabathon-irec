import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {respondToLead} from '../store/slices/leadsSlice';
import AppText from './AppText';
import Avatar from './Avatar';
import Badge from './Badge';
import Button from './Button';
import Card from './Card';

/**
 * A request as the developer sees it.
 *
 * The phone and email are always rendered, but until the developer accepts, what the
 * server sent is a starred placeholder — `contact_visible` says which. Nothing is
 * hidden client-side, so there is no real value sitting in the payload to leak.
 */
const BrokerLeadCard = ({lead, propertyName, onPress}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const respondStatus = useAppSelector(state => state.leads.respondStatus);

  const broker = lead.broker;
  if (!broker) {
    return null;
  }

  const visible = lead.contact_visible;
  // Driven by the stage, not by the gate: the decision is what opens the gate, so
  // reading `contact_visible` here would hide the buttons that unlock it.
  const canRespond = lead.status === 'interested';
  const isRequest = lead.status !== 'viewed';
  const Wrapper = onPress ? TouchableOpacity : View;

  const tone = {accepted: 'success', declined: 'danger', interested: 'warning'}[lead.status];
  const label = {accepted: 'Accepted', declined: 'Rejected', interested: 'Requested'}[lead.status];

  const contactRow = (icon, value) =>
    !!value && (
      <View style={styles.row}>
        <Icon
          name={icon}
          size={moderateScale(14)}
          color={visible ? colors.primary : colors.textMuted}
        />
        <AppText
          variant="caption"
          color={visible ? colors.textSecondary : colors.textMuted}
          style={{marginLeft: moderateScale(6)}}>
          {value}
        </AppText>
      </View>
    );

  return (
    <Wrapper {...(onPress ? {activeOpacity: 0.85, onPress} : {})}>
      <Card style={{marginBottom: spacing.sm}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          {/* The passport photo from registration — PartnerResource resolves it from
              the profile, or the broker's later avatar if they changed it. */}
          <Avatar uri={broker.photo_url} name={broker.name} size="md" />
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

        {isRequest ? (
          <View
            style={{
              marginTop: spacing.sm,
              paddingTop: spacing.sm,
              borderTopWidth: 1,
              borderTopColor: colors.border,
            }}>
            {contactRow('call-outline', broker.mobile)}
            {contactRow('mail-outline', broker.email)}
            {contactRow('shield-checkmark-outline', broker.rera_number)}

            {!visible && (
              <View style={[styles.row, {marginTop: moderateScale(2)}]}>
                <Icon
                  name="lock-closed-outline"
                  size={moderateScale(13)}
                  color={colors.textMuted}
                />
                <AppText
                  variant="caption"
                  color={colors.textMuted}
                  style={{marginLeft: moderateScale(6), flex: 1}}>
                  Full details shared once you accept
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
          <View style={[styles.row, {marginTop: spacing.sm}]}>
            <Icon name="eye-outline" size={moderateScale(13)} color={colors.textMuted} />
            <AppText
              variant="caption"
              color={colors.textMuted}
              style={{marginLeft: moderateScale(6), flex: 1}}>
              Viewed your listing — no introduction requested yet
            </AppText>
          </View>
        )}
      </Card>
    </Wrapper>
  );
};

const styles = {
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: moderateScale(6),
  },
};

export default BrokerLeadCard;
