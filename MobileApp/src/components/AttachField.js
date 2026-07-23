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

export const AttachBox = ({uri, onPick, placeholder = 'Tap to upload', error}) => {
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
      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(4)}}>
          {error}
        </AppText>
      )}
    </View>
  );
};

export const AttachPill = ({uri, onPick, label = 'Attach'}) => {
  const {colors, radius} = useAppTheme();

  return (
    <TouchableOpacity
      activeOpacity={0.85}
      onPress={() => pickImage(onPick)}
      style={{
        borderWidth: 1.5,
        borderStyle: 'dashed',
        borderColor: colors.primary,
        borderRadius: radius.sm,
        paddingHorizontal: moderateScale(10),
        alignItems: 'center',
        justifyContent: 'center',
        flexDirection: 'row',
        height: verticalScale(42),
        backgroundColor: colors.primarySoft,
      }}>
      <Icon name={uri ? 'checkmark-circle' : 'add'} size={moderateScale(14)} color={colors.primaryDark} />
      <AppText variant="captionMedium" color={colors.primaryDark} style={{marginLeft: moderateScale(4)}}>
        {uri ? 'Attached' : label}
      </AppText>
    </TouchableOpacity>
  );
};
