import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {roundedRadius, useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Button, Card, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {clearAuthError} from '../../store/slices/authSlice';

const PendingApprovalScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  // The registered user as the API returned it — approval is an admin action,
  // so this screen can only report status, never grant it.
  const user = useAppSelector(state => state.auth.user);
  const status = useAppSelector(state => state.auth.registrationStatus);
  const role = useAppSelector(state => state.auth.role);

  return (
    <ScreenContainer edges={['top', 'bottom']} scroll style={{justifyContent: 'center'}}>
      <View style={{alignItems: 'center', marginBottom: spacing.xl}}>
        <View
          style={{
            width: moderateScale(96),
            height: moderateScale(96),
            // Rounded, not square: this disc is a marker sitting on the page rather than
            // a panel, and squared it read as an empty tile behind the icon.
            borderRadius: roundedRadius.statusIcon,
            backgroundColor: colors.primarySoft,
            alignItems: 'center',
            justifyContent: 'center',
            marginBottom: spacing.lg,
          }}>
          <Icon name="time-outline" size={moderateScale(44)} color={colors.primaryDark} />
        </View>
        <AppText variant="h1" align="center">
          Application Submitted
        </AppText>
        <AppText
          variant="body"
          color={colors.textSecondary}
          align="center"
          style={{marginTop: spacing.xs}}>
          Your registration is pending admin approval. We'll notify you the moment your account
          is verified.
        </AppText>
      </View>

      {user && (
        <Card style={{marginBottom: spacing.xl}}>
          <View style={{flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start'}}>
            <View style={{flexDirection: 'row', alignItems: 'center', flex: 1}}>
              <Avatar uri={user.avatar_url} name={user.name} size="md" />
              <View style={{marginLeft: spacing.sm, flex: 1}}>
                <AppText variant="h3" numberOfLines={1}>
                  {user.name}
                </AppText>
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginTop: moderateScale(2)}}>
                  {user.email}
                </AppText>
              </View>
            </View>
            <Badge
              label={status === 'rejected' ? 'Rejected' : 'Pending'}
              tone={status === 'rejected' ? 'danger' : 'warning'}
            />
          </View>
          <View style={{marginTop: spacing.md}}>
            <AppText variant="caption" color={colors.textMuted}>
              {user.mobile}
            </AppText>
          </View>
        </Card>
      )}

      <Button
        label="Back to Sign In"
        variant="outline"
        icon="arrow-back-outline"
        onPress={() => {
          dispatch(clearAuthError());
          // Brokers arrived here via EmailOtpVerify; developers via the shared
          // Login screen. Send each back the way they came.
          navigation.replace(role === 'broker' ? 'EmailOtpLogin' : 'Login');
        }}
      />
    </ScreenContainer>
  );
};

export default PendingApprovalScreen;
