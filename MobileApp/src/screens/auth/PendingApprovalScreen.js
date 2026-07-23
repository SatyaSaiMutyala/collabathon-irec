import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Avatar, Badge, Button, Card, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {approveRegistration} from '../../store/slices/authSlice';

const PendingApprovalScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const broker = useAppSelector(state => state.auth.broker);
  const status = useAppSelector(state => state.auth.registrationStatus);

  return (
    <ScreenContainer edges={['top', 'bottom']} style={{justifyContent: 'center'}}>
      <View style={{alignItems: 'center', marginBottom: spacing.xl}}>
        <View
          style={{
            width: moderateScale(96),
            height: moderateScale(96),
            borderRadius: moderateScale(48),
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

      {broker && (
        <Card style={{marginBottom: spacing.xl}}>
          <View style={{flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start'}}>
            <View style={{flexDirection: 'row', alignItems: 'center', flex: 1}}>
              <Avatar uri={broker.photoAttachment} name={broker.fullNameAsRera} size="md" />
              <View style={{marginLeft: spacing.sm, flex: 1}}>
                <AppText variant="h3" numberOfLines={1}>
                  {broker.suffix ? `${broker.suffix} ` : ''}
                  {broker.fullNameAsRera}
                </AppText>
                <AppText
                  variant="caption"
                  color={colors.textSecondary}
                  style={{marginTop: moderateScale(2)}}>
                  {broker.emailId}
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
              {broker.mobileNumber}
            </AppText>
          </View>
        </Card>
      )}

      <Button
        label="I've Been Approved (Demo)"
        variant="outline"
        icon="checkmark-circle-outline"
        onPress={() => {
          dispatch(approveRegistration());
          navigation.replace('Login');
        }}
      />
    </ScreenContainer>
  );
};

export default PendingApprovalScreen;
