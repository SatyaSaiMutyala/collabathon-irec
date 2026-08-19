import React from 'react';
import {ActivityIndicator, Modal, Pressable, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const OptionRow = ({icon, label, onPress, isBusy, isLast}) => {
  const {colors, spacing} = useAppTheme();
  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      disabled={isBusy}
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.md,
        borderBottomWidth: isLast ? 0 : 1,
        borderBottomColor: colors.border,
      }}>
      <View
        style={{
          width: moderateScale(36),
          height: moderateScale(36),
          borderRadius: moderateScale(999),
          backgroundColor: colors.primarySoft,
          alignItems: 'center',
          justifyContent: 'center',
        }}>
        <Icon name={icon} size={moderateScale(17)} color={colors.primaryDark} />
      </View>
      <AppText variant="bodyMedium" style={{marginLeft: spacing.sm, flex: 1}}>
        {label}
      </AppText>
      {isBusy ? (
        <ActivityIndicator size="small" color={colors.primary} />
      ) : (
        <Icon name="chevron-forward" size={moderateScale(16)} color={colors.textMuted} />
      )}
    </TouchableOpacity>
  );
};

/**
 * Two ways to set the home screen's location — GPS (current position, reverse-
 * geocoded) or a full map screen with a search box (see MapPickerScreen). Replaced a
 * fixed list of hardcoded cities: that list could only ever offer the handful of
 * places someone thought to type in ahead of time, not wherever a broker actually is.
 */
const LocationPickerSheet = ({visible, onClose, onUseCurrentLocation, onChooseFromMap, isDetecting}) => {
  const {colors, roundedRadius, spacing} = useAppTheme();

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent
      navigationBarTranslucent>
      <Pressable style={{flex: 1, backgroundColor: colors.overlayStrong}} onPress={onClose}>
        <Pressable
          onPress={e => e.stopPropagation()}
          style={{
            marginTop: 'auto',
            backgroundColor: colors.card,
            borderTopLeftRadius: roundedRadius.sheet,
            borderTopRightRadius: roundedRadius.sheet,
            paddingHorizontal: spacing.lg,
            paddingTop: spacing.sm,
            paddingBottom: spacing.xxl,
          }}>
          <View
            style={{
              alignSelf: 'center',
              width: moderateScale(36),
              height: moderateScale(4),
              borderRadius: 0,
              backgroundColor: colors.border,
              marginBottom: spacing.md,
            }}
          />

          <AppText variant="h3" style={{marginBottom: spacing.xs}}>
            Set your location
          </AppText>

          <OptionRow
            icon="locate"
            label="Current location"
            isBusy={isDetecting}
            onPress={onUseCurrentLocation}
          />
          <OptionRow icon="map-outline" label="Choose from map" onPress={onChooseFromMap} isLast />
        </Pressable>
      </Pressable>
    </Modal>
  );
};

export default LocationPickerSheet;
