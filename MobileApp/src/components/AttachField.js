import React from 'react';
import {Alert, Image, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import {moderateScale, verticalScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

function pickImage(onPicked) {
  const handleResponse = response => {
    const uri = response?.assets?.[0]?.uri;
    if (uri) {
      onPicked(uri);
    }
  };
  Alert.alert('Attach Photo', 'Choose an option', [
    {text: 'Take Photo', onPress: () => launchCamera({mediaType: 'photo', quality: 0.7}, handleResponse)},
    {
      text: 'Choose from Library',
      onPress: () => launchImageLibrary({mediaType: 'photo', quality: 0.7}, handleResponse),
    },
    {text: 'Cancel', style: 'cancel'},
  ]);
}

export const AttachBox = ({uri, onPick, onRemove, placeholder = 'Tap to upload', error}) => {
  const {colors, radius, spacing} = useAppTheme();

  return (
    <View>
      <TouchableOpacity activeOpacity={0.85} onPress={() => pickImage(onPick)}>
        {uri ? (
          <Image
            source={{uri}}
            style={{width: '100%', height: moderateScale(150), borderRadius: radius.md}}
            resizeMode="cover"
          />
        ) : (
          <View
            style={{
              borderWidth: 1.5,
              borderStyle: 'dashed',
              borderColor: error ? colors.danger : colors.primary,
              borderRadius: radius.md,
              paddingVertical: spacing.lg,
              alignItems: 'center',
              justifyContent: 'center',
              backgroundColor: colors.primarySoft,
            }}>
            <Icon name="add" size={moderateScale(20)} color={colors.primaryDark} />
            <AppText variant="caption" color={colors.primaryDark} style={{marginTop: moderateScale(4)}}>
              {placeholder}
            </AppText>
          </View>
        )}
      </TouchableOpacity>

      {uri && onRemove && (
        <TouchableOpacity
          activeOpacity={0.8}
          onPress={onRemove}
          hitSlop={8}
          style={{
            position: 'absolute',
            top: moderateScale(8),
            right: moderateScale(8),
            width: moderateScale(26),
            height: moderateScale(26),
            borderRadius: moderateScale(13),
            backgroundColor: colors.overlayStrong,
            alignItems: 'center',
            justifyContent: 'center',
          }}>
          <Icon name="close" size={moderateScale(15)} color={colors.white} />
        </TouchableOpacity>
      )}

      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(4)}}>
          {error}
        </AppText>
      )}
    </View>
  );
};

export const AttachPill = ({uri, onPick, onRemove, label = 'Attach'}) => {
  const {colors, radius} = useAppTheme();

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        borderWidth: 1.5,
        borderStyle: 'dashed',
        borderColor: colors.primary,
        borderRadius: radius.sm,
        height: verticalScale(42),
        backgroundColor: colors.primarySoft,
        paddingLeft: moderateScale(uri ? 5 : 10),
        paddingRight: moderateScale(uri && onRemove ? 6 : 10),
      }}>
      <TouchableOpacity
        activeOpacity={0.85}
        onPress={() => pickImage(onPick)}
        style={{flexDirection: 'row', alignItems: 'center'}}>
        {uri ? (
          <Image
            source={{uri}}
            style={{width: moderateScale(28), height: moderateScale(28), borderRadius: moderateScale(6)}}
            resizeMode="cover"
          />
        ) : (
          <Icon name="add" size={moderateScale(14)} color={colors.primaryDark} />
        )}
        <AppText variant="captionMedium" color={colors.primaryDark} style={{marginLeft: moderateScale(5)}}>
          {uri ? 'Attached' : label}
        </AppText>
      </TouchableOpacity>

      {uri && onRemove && (
        <TouchableOpacity activeOpacity={0.8} onPress={onRemove} hitSlop={8} style={{marginLeft: moderateScale(6)}}>
          <Icon name="close-circle" size={moderateScale(17)} color={colors.textMuted} />
        </TouchableOpacity>
      )}
    </View>
  );
};
