import React from 'react';
import {TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Card, InfoRow, ScreenContainer} from '../../components';
import {getBrokerById} from '../../data/mockLeads';
import {getProjectById} from '../../data/mockDevelopers';
import {useEffectiveLeads} from '../../hooks/useDeveloperLeads';

const STATUS_BADGE = {
  accepted: {label: 'Accepted', tone: 'success'},
  declined: {label: 'Rejected', tone: 'danger'},
  interested: {label: 'Interested', tone: 'warning'},
};

const BrokerProfileScreen = ({route, navigation}) => {
  const {colors, spacing} = useAppTheme();
  const {brokerId, projectId} = route.params;
  const broker = getBrokerById(brokerId);
  const project = getProjectById(projectId);
  const leads = useEffectiveLeads(projectId ? [projectId] : []);
  const lead = leads.find(l => l.brokerId === brokerId);
  const status = lead?.effectiveStatus;

  if (!broker) {
    return (
      <ScreenContainer edges={['top']}>
        <AppText variant="body">Broker not found.</AppText>
      </ScreenContainer>
    );
  }

  const showContact = status === 'interested' || status === 'accepted';
  const badge = STATUS_BADGE[status];

  return (
    <ScreenContainer edges={['top']}>
      <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm, marginBottom: spacing.lg}}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h3" style={{marginLeft: spacing.sm}}>
          Broker Details
        </AppText>
      </View>

      <Card>
        <View style={{alignItems: 'center'}}>
          <Avatar name={broker.name} size="xl" />
          <AppText variant="h2" align="center" style={{marginTop: spacing.md}}>
            {broker.name}
          </AppText>
          <AppText variant="body" color={colors.textMuted} align="center">
            {broker.company}
          </AppText>
          {badge && <View style={{marginTop: spacing.sm}}><Badge label={badge.label} tone={badge.tone} /></View>}
          {project && (
            <AppText variant="caption" color={colors.textMuted} align="center" style={{marginTop: spacing.xs}}>
              on {project.name}
            </AppText>
          )}
        </View>
      </Card>

      <Card style={{marginTop: spacing.md}}>
        {showContact ? (
          <>
            <InfoRow icon="call-outline" label="Mobile" value={broker.mobile} />
            <InfoRow icon="mail-outline" label="Email" value={broker.email} />
            <InfoRow icon="shield-checkmark-outline" label="RERA Number" value={broker.reraNumber} />
          </>
        ) : (
          <View style={{flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.sm}}>
            <Icon name="lock-closed-outline" size={moderateScale(16)} color={colors.textMuted} />
            <AppText variant="caption" color={colors.textMuted} style={{marginLeft: spacing.sm, flex: 1}}>
              Contact details unlock once this broker marks interest in the property.
            </AppText>
          </View>
        )}
      </Card>
    </ScreenContainer>
  );
};

export default BrokerProfileScreen;
