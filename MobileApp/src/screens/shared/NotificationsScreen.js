import React, {useEffect} from 'react';
import {FlatList, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  EmptyState,
  NotificationRowSkeleton,
  ScreenContainer,
  SkeletonList,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {markAllRead} from '../../store/slices/notificationsSlice';
import {fetchNotificationLeads} from '../../store/slices/leadsSlice';
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

const NotificationRow = ({item, onPress}) => {
  const {colors, spacing, roundedRadius} = useAppTheme();
  const Container = onPress ? TouchableOpacity : View;

  return (
    <Container
      activeOpacity={onPress ? 0.7 : undefined}
      onPress={onPress}
      style={{
        flexDirection: 'row',
        paddingVertical: spacing.md,
        borderBottomWidth: 1,
        borderBottomColor: colors.border,
      }}>
      {/* The tone disc is a quiet marker beside the text, not a focal element —
          kept small enough that the title stays the first thing read. */}
      <View
        style={{
          width: moderateScale(30),
          height: moderateScale(30),
          borderRadius: roundedRadius.notification,
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: TONE_ICON_BG[item.tone](colors),
        }}>
        <Icon name={item.icon} size={moderateScale(15)} color={TONE_ICON_COLOR[item.tone](colors)} />
      </View>

      <View style={{flex: 1, marginLeft: spacing.sm}}>
        <View style={{flexDirection: 'row', alignItems: 'center'}}>
          {item.isUnread && (
            <View
              style={{
                width: moderateScale(6),
                height: moderateScale(6),
                borderRadius: roundedRadius.notification,
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
    </Container>
  );
};

const NotificationsScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();
  const role = useAppSelector(state => state.auth.role);
  const notifications = useNotifications();
  // Notifications are derived from their own unfiltered lead fetch (leads.notifications),
  // kept apart from the inbox/requests screens' filtered `leads.list` — see leadsSlice.
  const leadsStatus = useAppSelector(state => state.leads.notifications.status);
  const isFirstLoad = leadsStatus === 'idle' || (leadsStatus === 'loading' && notifications.length === 0);

  // A developer's notifications are about a broker's interaction with one of their
  // own listings, so they land on PropertyLeadsScreen — it (unlike BrokerDetailScreen)
  // fetches that lead list fresh on its own rather than depending on whatever's
  // already sitting in Redux, so it can't show a false "no longer available" for a
  // lead this screen's own unfiltered fetch never loaded into the same bucket the
  // other screens read from. A broker's notifications are about their own request on
  // a property, so those land on the property itself — same place RequestsScreen and
  // PartnersScreen already navigate to for the same lead.
  const handlePress = item => {
    if (!item.propertyId) {
      return;
    }
    if (role === 'developer') {
      navigation.navigate('PropertyLeads', {projectId: item.propertyId});
      return;
    }
    navigation.navigate('ProjectDetail', {projectId: item.propertyId});
  };

  useEffect(() => {
    dispatch(fetchNotificationLeads());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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

      {isFirstLoad ? (
        <SkeletonList count={6} renderItem={() => <NotificationRowSkeleton />} />
      ) : (
      <FlatList
        data={notifications}
        keyExtractor={item => item.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        renderItem={({item}) => (
          <NotificationRow item={item} onPress={() => handlePress(item)} />
        )}
        ListEmptyComponent={
          <EmptyState
            icon="notifications-off-outline"
            title="No notifications"
            message="Approvals, new requests and developer decisions land here."
          />
        }
      />
      )}
    </ScreenContainer>
  );
};

export default NotificationsScreen;
