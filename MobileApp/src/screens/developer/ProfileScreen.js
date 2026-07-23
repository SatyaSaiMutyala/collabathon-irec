import React from 'react';
import {ScrollView, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Button, Card, ScreenContainer, StatRow} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {logOut} from '../../store/slices/authSlice';
import {getDeveloperById} from '../../data/mockDevelopers';

const InfoRow = ({icon, label, value}) => {
  const {colors, spacing} = useAppTheme();
  return (
    <View style={{flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.xs}}>
      <Icon name={icon} size={moderateScale(16)} color={colors.primary} />
      <View style={{marginLeft: spacing.sm, flex: 1}}>
        <AppText variant="caption" color={colors.textMuted}>
          {label}
        </AppText>
        <AppText variant="bodyMedium">{value}</AppText>
      </View>
    </View>
  );
};

const ProfileScreen = () => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const developer = useAppSelector(state => state.auth.developer);
  const company = getDeveloperById(developer?.developerId);

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
          Profile
        </AppText>

        <Card style={{paddingVertical: spacing.md}}>
          <View style={{alignItems: 'center'}}>
            <Avatar
              uri={company?.logo}
              name={company?.name}
              size="lg"
              ringColor={company?.verified ? colors.primary : colors.border}
              showVerified={company?.verified}
            />
            <AppText variant="h3" align="center" style={{marginTop: spacing.sm}}>
              {company?.name ?? 'Your Company'}
            </AppText>
            <AppText variant="caption" color={colors.textSecondary} style={{marginTop: moderateScale(1)}}>
              {developer?.contactName}
            </AppText>
            {company?.verified && (
              <View style={{marginTop: spacing.xs}}>
                <Badge label="Verified Developer" tone="success" />
              </View>
            )}
          </View>

          {company && (
            <View
              style={{
                marginTop: spacing.md,
                paddingTop: spacing.sm,
                borderTopWidth: 1,
                borderTopColor: colors.border,
              }}>
              <StatRow
                stats={[
                  {value: String(company.projects.length), label: 'Properties'},
                  {value: `${company.cpPayoutPercent}%`, label: 'CP Payout'},
                  {value: company.city, label: 'City'},
                ]}
              />
            </View>
          )}
        </Card>

        <Card style={{marginTop: spacing.md, paddingVertical: spacing.xs}}>
          <InfoRow icon="call-outline" label="Contact Mobile" value={developer?.mobile ?? '—'} />
          <InfoRow icon="mail-outline" label="Contact Email" value={developer?.email || '—'} />
          <InfoRow icon="shield-checkmark-outline" label="RERA Number" value={company?.reraNumber ?? '—'} />
        </Card>

        <Button
          label="Log Out"
          variant="outline"
          icon="log-out-outline"
          style={{marginTop: spacing.lg}}
          onPress={() => dispatch(logOut())}
        />
      </ScrollView>
    </ScreenContainer>
  );
};

export default ProfileScreen;
