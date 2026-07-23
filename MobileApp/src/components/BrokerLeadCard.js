import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import {useAppDispatch} from '../store/hooks';
import {respondToLead} from '../store/slices/developerLeadsSlice';
import {getBrokerById} from '../data/mockLeads';
import AppText from './AppText';
import Avatar from './Avatar';
import Badge from './Badge';
import Button from './Button';
import Card from './Card';

const BrokerLeadCard = ({lead, propertyName, onPress}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const broker = getBrokerById(lead.brokerId);
  if (!broker) {
    return null;
  }

  const showContact = lead.effectiveStatus === 'interested' || lead.effectiveStatus === 'accepted';
  const showLockedNotice = lead.effectiveStatus === 'viewed';
  const Wrapper = onPress ? TouchableOpacity : View;

  return (
    <Wrapper {...(onPress ? {activeOpacity: 0.85, onPress} : {})}>
    <Card style={{marginBottom: spacing.sm}}>
      <View style={{flexDirection: 'row', alignItems: 'center'}}>
        <Avatar name={broker.name} size="md" />
        <View style={{flex: 1, marginLeft: spacing.sm}}>
          <AppText variant="bodyMedium" numberOfLines={1}>
            {broker.name}
          </AppText>
          <AppText variant="caption" color={colors.textMuted} numberOfLines={1}>
            {broker.company}
          </AppText>
          {propertyName && (
            <AppText variant="caption" color={colors.primaryDark} numberOfLines={1} style={{marginTop: moderateScale(2)}}>
              {propertyName}
            </AppText>
          )}
        </View>
        {lead.effectiveStatus === 'accepted' && <Badge label="Accepted" tone="success" />}
        {lead.effectiveStatus === 'declined' && <Badge label="Rejected" tone="danger" />}
        {lead.effectiveStatus === 'interested' && <Badge label="Interested" tone="warning" />}
      </View>

      {showContact ? (
        <View style={{marginTop: spacing.sm, paddingTop: spacing.sm, borderTopWidth: 1, borderTopColor: colors.border}}>
          <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: moderateScale(6)}}>
            <Icon name="call-outline" size={moderateScale(14)} color={colors.primary} />
            <AppText variant="caption" color={colors.textSecondary} style={{marginLeft: moderateScale(6)}}>
              {broker.mobile}
            </AppText>
          </View>
          <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: moderateScale(6)}}>
            <Icon name="mail-outline" size={moderateScale(14)} color={colors.primary} />
            <AppText variant="caption" color={colors.textSecondary} style={{marginLeft: moderateScale(6)}}>
              {broker.email}
            </AppText>
          </View>
          <View style={{flexDirection: 'row', alignItems: 'center'}}>
            <Icon name="shield-checkmark-outline" size={moderateScale(14)} color={colors.primary} />
            <AppText variant="caption" color={colors.textSecondary} style={{marginLeft: moderateScale(6)}}>
              {broker.reraNumber}
            </AppText>
          </View>

          {lead.effectiveStatus === 'interested' && (
            <View style={{flexDirection: 'row', marginTop: spacing.md}}>
              <View style={{flex: 1, marginRight: spacing.xs}}>
                <Button
                  label="Decline"
                  variant="outline"
                  size="md"
                  onPress={() =>
                    dispatch(respondToLead({projectId: lead.projectId, brokerId: broker.id, status: 'declined'}))
                  }
                />
              </View>
              <View style={{flex: 1, marginLeft: spacing.xs}}>
                <Button
                  label="Accept"
                  size="md"
                  onPress={() =>
                    dispatch(respondToLead({projectId: lead.projectId, brokerId: broker.id, status: 'accepted'}))
                  }
                />
              </View>
            </View>
          )}
        </View>
      ) : showLockedNotice ? (
        <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm}}>
          <Icon name="lock-closed-outline" size={moderateScale(13)} color={colors.textMuted} />
          <AppText variant="caption" color={colors.textMuted} style={{marginLeft: moderateScale(6)}}>
            Viewed only — contact hidden until this broker marks interest
          </AppText>
        </View>
      ) : null}
    </Card>
    </Wrapper>
  );
};

export default BrokerLeadCard;
