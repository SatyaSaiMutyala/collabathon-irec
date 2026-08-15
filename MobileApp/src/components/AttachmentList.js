import React from 'react';
import {Alert, Linking, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Card from './Card';

/**
 * Renders the non-photo half of `property_media` — brochures, price lists, plans,
 * RERA certificates, payment schedules, videos and virtual tours.
 *
 * These open in the device's own browser/viewer rather than in an in-app WebView: the
 * files are PDFs, video links and third-party tour URLs, and the OS already has a
 * handler for each. Trying to render them inline would mean shipping a viewer per type.
 */
const AttachmentList = ({items = []}) => {
  const {colors, spacing, radius} = useAppTheme();

  if (!items.length) {
    return null;
  }

  const open = async item => {
    // Deliberately no canOpenURL() gate. On Android 11+ it answers false for http/https
    // unless the app declares a matching <queries> intent, so it reports "unsupported"
    // on devices that open the link perfectly well. openURL throws when there genuinely
    // is no handler, which is the signal worth acting on.
    try {
      await Linking.openURL(item.url);
    } catch {
      Alert.alert(
        'Cannot open',
        'No app on this device could open this file, or the link is no longer valid.',
      );
    }
  };

  return (
    <Card style={{paddingVertical: spacing.xxs}}>
      {items.map((item, index) => (
        <TouchableOpacity
          key={item.id ?? `${item.kind}-${index}`}
          activeOpacity={0.7}
          onPress={() => open(item)}
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            paddingVertical: spacing.sm,
            borderTopWidth: index === 0 ? 0 : 1,
            borderTopColor: colors.border,
          }}>
          <View
            style={{
              width: moderateScale(36),
              height: moderateScale(36),
              borderRadius: radius.sm,
              backgroundColor: colors.primarySoft,
              alignItems: 'center',
              justifyContent: 'center',
            }}>
            <Icon name={item.icon} size={moderateScale(18)} color={colors.primaryDark} />
          </View>

          <View style={{flex: 1, marginLeft: spacing.sm}}>
            <AppText variant="bodyMedium" numberOfLines={1}>
              {item.label}
            </AppText>
            {!!item.caption && (
              <AppText
                variant="caption"
                color={colors.textMuted}
                numberOfLines={1}
                style={{marginTop: moderateScale(1)}}>
                {item.caption}
              </AppText>
            )}
          </View>

          <Icon name="open-outline" size={moderateScale(17)} color={colors.textMuted} />
        </TouchableOpacity>
      ))}
    </Card>
  );
};

export default AttachmentList;
