import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {roundedRadius, useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Button, Card, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {resetAuth} from '../../store/slices/authSlice';

/**
 * Reached only by a broker whose step-3 submit just went through, or one who
 * reopened the app while still `pending` (see RootNavigator's `pendingApproval`
 * branch and EmailOtpVerifyScreen/OtpVerifyScreen's own 403 handling). A rejected
 * broker never lands here any more — verifyOtp/verifyEmailOtp drop them back into
 * `draft` instead, straight onto CompleteProfileScreen with the reason shown, so
 * they can fix and resubmit rather than dead-ending on a status page.
 */
const PendingApprovalScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  // The registered user as the API returned it — approval is an admin action,
  // so this screen can only report status, never grant it.
  const user = useAppSelector(state => state.auth.user);

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
            <Badge label="Pending" tone="warning" />
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
          // resetAuth, not clearAuthError: this account's user/token stayed in Redux
          // otherwise, and CompleteProfileScreen's prefill (buildInitialForm) reads
          // identity.mobile ahead of the freshly-verified route param — so signing in
          // as a *different* number right after landed back on this stale one instead
          // of the one just OTP-verified. A full reset is what "back to sign in"
          // actually means here: this session is done, the next one starts clean.
          dispatch(resetAuth());
          // Only a broker ever lands on this screen (see the docblock above) — back
          // to Welcome, not straight to a specific OTP screen, so this stays correct
          // regardless of the admin's cp_login_method (email vs mobile) instead of
          // hardcoding one. Never the old password Login screen — that's the
          // developer-only flow this account never belonged to.
          navigation.replace('Welcome');
        }}
      />
    </ScreenContainer>
  );
};

export default PendingApprovalScreen;
