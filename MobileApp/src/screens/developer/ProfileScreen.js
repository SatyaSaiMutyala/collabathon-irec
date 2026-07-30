import React from 'react';
import {ScrollView, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Button, Card, ScreenContainer, StatRow} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {logout} from '../../store/slices/authSlice';

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
  const user = useAppSelector(state => state.auth.user);
  // DeveloperResource's own shape (snake_case). This screen used to read a camelCase
  // mock-data object with a `projects` array; that array has no API equivalent, so the
  // property tally comes from the `properties_count` the resource exposes when counted.
  const company = user?.developer;
  // Screen renders `developer.*` for the contact person and `company.*` for the firm.
  const developer = {contactName: user?.name, mobile: user?.mobile, email: user?.email};

  return (
    <ScreenContainer edges={['top']}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{paddingBottom: spacing.xxl}}>
        <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
          Profile
        </AppText>

        <Card style={{paddingVertical: spacing.md}}>
          <View style={{alignItems: 'center'}}>
            <Avatar
              uri={company?.logo_url}
              name={company?.company_name}
              size="lg"
              ringColor={company?.verified ? colors.primary : colors.border}
              showVerified={company?.verified}
            />
            <AppText variant="h3" align="center" style={{marginTop: spacing.sm}}>
              {company?.company_name ?? 'Your Company'}
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
                  {value: String(company.properties_count ?? '—'), label: 'Properties'},
                  {value: `${company.cp_payout_percent ?? 0}%`, label: 'CP Payout'},
                  {value: company.city ?? '—', label: 'City'},
                ]}
              />
            </View>
          )}
        </Card>

        <Card style={{marginTop: spacing.md, paddingVertical: spacing.xs}}>
          <InfoRow icon="call-outline" label="Contact Mobile" value={developer?.mobile ?? '—'} />
          <InfoRow icon="mail-outline" label="Contact Email" value={developer?.email || '—'} />
          <InfoRow icon="shield-checkmark-outline" label="RERA Number" value={company?.rera_number ?? '—'} />
        </Card>

        <Button
          label="Log Out"
          variant="outline"
          icon="log-out-outline"
          style={{marginTop: spacing.lg}}
          onPress={() => dispatch(logout())}
        />
      </ScrollView>
    </ScreenContainer>
  );
};

export default ProfileScreen;
