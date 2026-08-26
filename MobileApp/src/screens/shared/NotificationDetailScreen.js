import React, {useEffect} from 'react';
import {ActivityIndicator, ScrollView, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../../theme/scaling';
import {useAppTheme} from '../../theme';
import {AppText, BackHeader, EmptyState, RemoteImage, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchAnnouncements} from '../../store/slices/notificationsSlice';

function formatSentAt(isoString) {
  const date = new Date(isoString);
  return Number.isNaN(date.getTime())
    ? ''
    : date.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) +
        ' · ' +
        date.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
}

/**
 * One admin broadcast in full — the whole image and the untruncated message.
 *
 * The list row deliberately clamps both (a two-line preview, a thumbnail), so this is
 * where the rest of it lives. Only broadcasts land here: every other row in the feed is
 * about a lead, and those already have a real destination of their own (the listing, or
 * that listing's partners) which is more useful than a copy of the notification text.
 *
 * Reads from the store rather than taking the announcement through route params, so a
 * push tapped from a cold start — where nothing has been fetched yet — lands on the
 * right thing instead of an empty screen.
 */
const NotificationDetailScreen = ({navigation, route}) => {
  const {colors, spacing, radius} = useAppTheme();
  const dispatch = useAppDispatch();

  const announcementId = Number(route.params?.announcementId);
  const announcement = useAppSelector(state =>
    state.notifications.announcements.find(item => item.id === announcementId),
  );
  const status = useAppSelector(state => state.notifications.status);

  // Nothing in the store yet — a cold start from a tapped push, rather than an
  // arrival from the list, which has already fetched.
  useEffect(() => {
    if (!announcement && status === 'idle') {
      dispatch(fetchAnnouncements());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const isLoading = !announcement && (status === 'idle' || status === 'loading');

  return (
    <ScreenContainer edges={['top']}>
      <BackHeader navigation={navigation} title="Notification" variant="h3" fallbackRoute="Main" />

      {isLoading ? (
        <View style={{paddingTop: spacing.xxl, alignItems: 'center'}}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : !announcement ? (
        <EmptyState
          icon="notifications-off-outline"
          title="Notification unavailable"
          message="This notification is no longer available."
        />
      ) : (
        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={{paddingBottom: spacing.xxl}}>
          {/* resizeMode 'contain', not 'cover': the point of opening this screen is to
              see the whole picture, and a cover crop of a wide banner hides the half
              the message is usually about. */}
          {announcement.image_url ? (
            <RemoteImage
              uri={announcement.image_url}
              resizeMode="contain"
              style={{
                width: '100%',
                height: moderateScale(200),
                borderRadius: radius.md,
                backgroundColor: colors.surface,
                marginBottom: spacing.lg,
              }}
              fallback={
                <View
                  style={{
                    width: '100%',
                    height: moderateScale(200),
                    borderRadius: radius.md,
                    backgroundColor: colors.surface,
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: spacing.lg,
                  }}>
                  <Icon name="image-outline" size={moderateScale(32)} color={colors.textMuted} />
                </View>
              }
            />
          ) : null}

          <AppText variant="h2">{announcement.title}</AppText>

          <AppText
            variant="caption"
            color={colors.textMuted}
            style={{marginTop: moderateScale(6)}}>
            {formatSentAt(announcement.created_at)}
          </AppText>

          <AppText
            variant="body"
            color={colors.textSecondary}
            style={{marginTop: spacing.md}}>
            {announcement.body}
          </AppText>
        </ScrollView>
      )}
    </ScreenContainer>
  );
};

export default NotificationDetailScreen;
