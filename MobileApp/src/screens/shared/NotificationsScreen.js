import React, {useEffect} from 'react';
import {FlatList, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, ScreenContainer} from '../../components';
import {useAppDispatch} from '../../store/hooks';
import {markAllRead} from '../../store/slices/notificationsSlice';
import {useNotifications} from '../../hooks/useNotifications';

const TONE_ICON_BG = {
  success: (colors) => colors.successSoft,
  danger: (colors) => colors.dangerSoft,
  warning: (colors) => colors.warningSoft,
  primary: (colors) => colors.primarySoft,
  neutral: (colors) => colors.surface,
};

const TONE_ICON_COLOR = {
  success: (colors) => colors.success,
  danger: (colors) => colors.danger,
  warning: (colors) => colors.warning,
  primary: (colors) => colors.primaryDark,
  neutral: (colors) => colors.textMuted,
};

const NotificationRow = ({item}) => {
  const {colors, spacing, radius} = useAppTheme();

  return (
    <View
      style={{
        flexDirection: 'row',
        paddingVertical: spacing.md,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
      }}>
      <View
        style={{
          width: moderateScale(38),
          height: moderateScale(38),
          borderRadius: radius.pill,
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: TONE_ICON_BG[item.tone](colors),
        }}>
        <Icon name={item.icon} size={moderateScale(18)} color={TONE_ICON_COLOR[item.tone](colors)} />
      </View>

      <View style={{flex: 1, marginLeft: spacing.sm}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          {item.isUnread && (
            <View
              style={{
                width: moderateScale(6),
                height: moderateScale(6),
                borderRadius: moderateScale(3),
                backgroundColor: colors.primary,
                marginRight: moderateScale(6),
              }}
            />
          )}
          <AppText variant="bodyMedium" style={{flex: 1}} numberOfLines={1}>
            {item.title}
          </AppText>
          <AppText variant="caption" color={colors.textMuted}>
            {item.timeAgo}
          </AppText>
        </View>
        <AppText variant="caption" color={colors.textSecondary} style={{marginTop: moderateScale(3)}}>
          {item.message}
        </AppText>
      </View>
    </View>
  );
};

const NotificationsScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const notifications = useNotifications();

  useEffect(() => {
    if (notifications.length > 0) {
      dispatch(markAllRead(notifications.map(item => item.id)));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [notifications.length]);

  return (
    <ScreenContainer edges={['top']}>
      <View style={{flexDirection: 'row', alignItems: 'center', marginTop: spacing.sm, marginBottom: spacing.md}}>
        <TouchableOpacity onPress={() => navigation.goBack()} hitSlop={10}>
          <Icon name="chevron-back" size={moderateScale(24)} color={colors.textPrimary} />
        </TouchableOpacity>
        <AppText variant="h3" style={{marginLeft: spacing.sm}}>
          Notifications
        </AppText>
      </View>

      <FlatList
        data={notifications}
        keyExtractor={item => item.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        renderItem={({item}) => <NotificationRow item={item} />}
        ListEmptyComponent={
          <View style={{alignItems: 'center', marginTop: spacing.xxl}}>
            <Icon name="notifications-off-outline" size={moderateScale(32)} color={colors.textMuted} />
            <AppText variant="body" color={colors.textMuted} style={{marginTop: spacing.sm}}>
              Nothing here yet.
            </AppText>
          </View>
        }
      />
    </ScreenContainer>
  );
};

export default NotificationsScreen;
