import React, {useEffect} from 'react';
import {FlatList, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {
  AppText,
  EmptyState,
  NotificationRowSkeleton,
  RemoteImage,
  ScreenContainer,
  SkeletonList,
} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchAnnouncements, markAllRead} from '../../store/slices/notificationsSlice';
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
  const {colors, spacing, radius, roundedRadius} = useAppTheme();
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
        {/* Clamped to two lines: this is a feed, and a long broadcast would otherwise
            push every row after it off the screen. The full text is on the detail
            screen this row opens. */}
        <AppText
          variant="caption"
          color={colors.textSecondary}
          style={{marginTop: moderateScale(3)}}
          numberOfLines={2}>
          {item.message}
        </AppText>

        {/* Inside the text column, below the message — not a trailing sibling of the
            row. As its own column it took width from the title row above, so a row with
            an image pulled its timestamp inwards and the times stopped lining up down
            the list.

            aspectRatio rather than a fixed height, so the banner scales with the screen
            instead of being tall on a small phone and squat on a large one. `cover`
            crops it to that band; the detail screen this row opens shows the whole
            image uncropped. Nothing renders when there is no image or the URL fails. */}
        {item.imageUrl ? (
          <RemoteImage
            uri={item.imageUrl}
            resizeMode="cover"
            style={{
              width: '100%',
              aspectRatio: 21 / 9,
              borderRadius: radius.md,
              marginTop: spacing.sm,
              backgroundColor: colors.surface,
            }}
          />
        ) : null}
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
    // A broadcast has no entity behind it, so it opens its own full-text screen
    // rather than a listing.
    if (item.announcementId) {
      navigation.navigate('NotificationDetail', {announcementId: item.announcementId});
      return;
    }
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
    dispatch(fetchAnnouncements());
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
        contentContainerStyle={[
          {paddingBottom: spacing.xxl},
          // flexGrow only while genuinely empty — otherwise a short real list would
          // get centred vertically too, instead of anchored to the top. This is what
          // lets EmptyState centre itself in the space below the header rather than
          // sitting docked right under it with everything below left empty.
          notifications.length === 0 && {flexGrow: 1, justifyContent: 'center'},
        ]}
        renderItem={({item}) => (
          // NotificationRow renders a plain View rather than a touchable when there is
          // nowhere to go, so a row with neither destination doesn't offer press
          // feedback that leads nowhere.
          <NotificationRow
            item={item}
            onPress={
              item.announcementId || item.propertyId ? () => handlePress(item) : undefined
            }
          />
        )}
        ListEmptyComponent={
          <EmptyState
            icon="notifications-off-outline"
            title="No notifications"
            message="Approvals, new requests and developer decisions land here."
            style={{marginTop: 0}}
          />
        }
      />
      )}
    </ScreenContainer>
  );
};

export default NotificationsScreen;
