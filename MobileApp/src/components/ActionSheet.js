import React from 'react';
import {Modal, Pressable, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

/**
 * The themed replacement for `Alert.alert(...)` wherever it was standing in for a
 * choice between a few actions (attach a photo, pick a file source, etc). The OS alert
 * cannot be restyled at all, so every place that needed one broke out of the app's own
 * look — this is the one place that look lives, reused rather than rebuilt per screen.
 *
 * `options` is the list of rows; Cancel is always appended and always just closes.
 * An option does not have to close the sheet itself — picking one calls `onClose` first
 * so the sheet is out of the way before whatever the option does (e.g. opening the
 * camera) takes over the screen.
 */
const ActionSheet = ({visible, onClose, title, options = [], cancelLabel = 'Cancel'}) => {
  const {colors, radius, spacing} = useAppTheme();

  const select = onPress => {
    onClose();
    onPress?.();
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <Pressable style={{flex: 1, backgroundColor: colors.overlayStrong}} onPress={onClose}>
        <Pressable
          onPress={event => event.stopPropagation()}
          style={{
            marginTop: 'auto',
            backgroundColor: colors.card,
            borderTopLeftRadius: radius.xl,
            borderTopRightRadius: radius.xl,
            paddingHorizontal: spacing.lg,
            paddingTop: spacing.sm,
            paddingBottom: spacing.xxl,
          }}>
          <View
            style={{
              alignSelf: 'center',
              width: moderateScale(36),
              height: moderateScale(4),
              borderRadius: moderateScale(2),
              backgroundColor: colors.border,
              marginBottom: spacing.md,
            }}
          />

          {!!title && (
            <AppText
              variant="h3"
              align="center"
              style={{marginBottom: spacing.sm}}>
              {title}
            </AppText>
          )}

          <View>
            {options.map((option, index) => {
              const isDanger = option.tone === 'danger';
              const tint = isDanger ? colors.danger : colors.primary;

              return (
                <TouchableOpacity
                  key={option.key ?? option.label}
                  activeOpacity={0.75}
                  onPress={() => select(option.onPress)}
                  style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                    paddingVertical: spacing.sm + moderateScale(2),
                    borderTopWidth: index === 0 ? 0 : 1,
                    borderTopColor: colors.border,
                  }}>
                  {!!option.icon && (
                    <Icon
                      name={option.icon}
                      size={moderateScale(18)}
                      color={tint}
                      style={{marginRight: spacing.sm}}
                    />
                  )}
                  <AppText variant="bodyMedium" color={isDanger ? colors.danger : colors.textPrimary}>
                    {option.label}
                  </AppText>
                </TouchableOpacity>
              );
            })}
          </View>

          <TouchableOpacity
            activeOpacity={0.75}
            onPress={onClose}
            style={{
              marginTop: spacing.sm,
              paddingVertical: spacing.sm + moderateScale(2),
              borderTopWidth: 1,
              borderTopColor: colors.border,
              alignItems: 'center',
            }}>
            <AppText variant="bodyMedium" color={colors.textSecondary}>
              {cancelLabel}
            </AppText>
          </TouchableOpacity>
        </Pressable>
      </Pressable>
    </Modal>
  );
};

export default ActionSheet;
