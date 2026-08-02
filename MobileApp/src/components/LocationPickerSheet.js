import React from 'react';
import {ActivityIndicator, Modal, Pressable, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const LocationPickerSheet = ({
  visible,
  onClose,
  cities,
  selectedCity,
  onSelectCity,
  onUseCurrentLocation,
  isDetecting,
}) => {
  const {colors, radius, spacing} = useAppTheme();

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <Pressable style={{flex: 1, backgroundColor: colors.overlayStrong}} onPress={onClose}>
        <Pressable
          onPress={e => e.stopPropagation()}
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
              borderRadius: 0,
              backgroundColor: colors.border,
              marginBottom: spacing.md,
            }}
          />

          <AppText variant="h3" style={{marginBottom: spacing.md}}>
            Choose Location
          </AppText>

          <TouchableOpacity
            activeOpacity={0.8}
            onPress={onUseCurrentLocation}
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              paddingVertical: spacing.sm,
              borderBottomWidth: 1,
              borderBottomColor: colors.border,
              marginBottom: spacing.xs,
            }}>
            <Icon name="locate-outline" size={moderateScale(17)} color={colors.primary} />
            <AppText variant="bodyMedium" color={colors.primary} style={{marginLeft: spacing.sm}}>
              Use Current Location
            </AppText>
            {isDetecting && (
              <ActivityIndicator size="small" color={colors.primary} style={{marginLeft: spacing.sm}} />
            )}
          </TouchableOpacity>

          {cities.map(city => {
            const isSelected = city === selectedCity;
            return (
              <TouchableOpacity
                key={city}
                activeOpacity={0.8}
                onPress={() => onSelectCity(city)}
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  paddingVertical: spacing.sm,
                }}>
                <View style={{flexDirection: 'row', alignItems: 'center'}}>
                  <Icon name="location-outline" size={moderateScale(17)} color={colors.textSecondary} />
                  <AppText variant="body" style={{marginLeft: spacing.sm}}>
                    {city}
                  </AppText>
                </View>
                {isSelected && (
                  <Icon name="checkmark-circle" size={moderateScale(18)} color={colors.primary} />
                )}
              </TouchableOpacity>
            );
          })}
        </Pressable>
      </Pressable>
    </Modal>
  );
};

export default LocationPickerSheet;
