import React, {useState} from 'react';
import {Alert, Image, Modal, Pressable, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import ImagePicker from 'react-native-image-crop-picker';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import ActionSheet from './ActionSheet';
import AppText from './AppText';

/** A profile photo is displayed in a square avatar everywhere, so it is cropped square. */
const AVATAR_CROP_SIZE = 800;

const CROP_OPTIONS = {
  width: AVATAR_CROP_SIZE,
  height: AVATAR_CROP_SIZE,
  cropping: true,
  cropperCircleOverlay: false,
  // The frame is fixed to the square the avatar will actually be, so what the user
  // frames is exactly what gets stored — no second, invisible crop on the server.
  freeStyleCropEnabled: false,
  mediaType: 'photo',
  compressImageQuality: 0.85,
  forceJpg: true,
  cropperToolbarTitle: 'Crop your photo',
  cropperChooseText: 'Use photo',
  cropperCancelText: 'Cancel',
};

/**
 * Cancelling a picker is a normal outcome, not a failure — the crop library rejects on
 * cancel, so only genuine errors are surfaced.
 */
function runCrop(promise, onPicked) {
  promise
    .then(image => image?.path && onPicked(image.path))
    .catch(error => {
      if (error?.code !== 'E_PICKER_CANCELLED') {
        Alert.alert('Could not use that photo', error?.message ?? 'Please try another image.');
      }
    });
}

/**
 * `crop` is opt-in per field rather than global: a passport photo becomes an avatar and
 * has to be square, but forcing a PAN card or a cancelled cheque through a square cropper
 * would make the user cut off the part of the document that matters.
 */
function pickFromCamera(onPicked, crop) {
  if (!crop) {
    launchCamera({mediaType: 'photo', quality: 0.7}, response => {
      // Cancelling is a normal outcome, same as the crop path below — nothing to alert.
      if (response.didCancel) {
        return;
      }

      const uri = response?.assets?.[0]?.uri;
      if (uri) {
        onPicked(uri);
        return;
      }

      // No uri and not a cancel means the camera itself couldn't be reached — most
      // commonly the Simulator, which has no camera hardware at all. Silently doing
      // nothing here reads as "the button is broken" rather than "there's no camera",
      // so this says which one it actually is and points at the path that does work.
      Alert.alert(
        'Camera unavailable',
        response.errorMessage ?? 'Could not open the camera on this device. Try Choose from Library instead.',
      );
    });
    return;
  }
  runCrop(ImagePicker.openCamera(CROP_OPTIONS), onPicked);
}

function pickFromLibrary(onPicked, crop) {
  if (!crop) {
    launchImageLibrary({mediaType: 'photo', quality: 0.7}, response => {
      const uri = response?.assets?.[0]?.uri;
      if (uri) {
        onPicked(uri);
      }
    });
    return;
  }
  runCrop(ImagePicker.openPicker(CROP_OPTIONS), onPicked);
}

/**
 * Full-screen look at what was attached.
 *
 * A 150pt strip is enough to confirm the right file was picked, not enough to check that
 * an ID number is legible before submitting — which is the one thing someone actually
 * wants to verify about a PAN card or a cheque. `contain`, never `cover`, so nothing is
 * cropped out of the very document being checked.
 */
const ImageViewer = ({uri, label, visible, onClose}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable style={{flex: 1, backgroundColor: colors.overlayStrong}} onPress={onClose}>
        <View
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            paddingHorizontal: spacing.lg,
            paddingTop: moderateScale(52),
            paddingBottom: spacing.sm,
          }}>
          <AppText variant="bodyMedium" color={colors.white} style={{flex: 1}} numberOfLines={1}>
            {label ?? 'Attachment'}
          </AppText>
          <TouchableOpacity onPress={onClose} hitSlop={12}>
            <Icon name="close" size={moderateScale(24)} color={colors.white} />
          </TouchableOpacity>
        </View>

        {!!uri && (
          <Image
            source={{uri}}
            style={{flex: 1, width: '100%'}}
            resizeMode="contain"
          />
        )}

        <AppText
          variant="caption"
          color={colors.white}
          align="center"
          style={{paddingVertical: spacing.lg, opacity: 0.7}}>
          Tap anywhere to close
        </AppText>
      </Pressable>
    </Modal>
  );
};

/** The two overlay actions on a filled attachment — same treatment everywhere. */
const OverlayAction = ({icon, onPress, accessibilityLabel}) => {
  const {colors} = useAppTheme();

  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      hitSlop={6}
      accessibilityLabel={accessibilityLabel}
      style={{
        width: moderateScale(28),
        height: moderateScale(28),
        backgroundColor: colors.overlayStrong,
        alignItems: 'center',
        justifyContent: 'center',
      }}>
      <Icon name={icon} size={moderateScale(15)} color={colors.white} />
    </TouchableOpacity>
  );
};

/**
 * An image attachment with a preview.
 *
 * Empty, it is a compact dashed target. Filled, it shows the picture at a size worth
 * looking at, with view and remove on it — so every attachment on the form reads the
 * same way, whether it is a passport photo or a cancelled cheque.
 *
 * Tapping a filled preview views it rather than re-opening the picker: once there is
 * something to look at, looking is the likely intent, and replacing is remove-then-pick.
 */
export const AttachBox = ({
  uri,
  onPick,
  onRemove,
  placeholder = 'Tap to upload',
  error,
  crop = false,
  /** Shown in the viewer header, so a full-screen image says which field it came from. */
  label,
  /** Documents sit under their own text field, so they get a shorter strip than the photo. */
  height = 150,
}) => {
  const {colors, radius, spacing} = useAppTheme();
  const [isViewing, setIsViewing] = useState(false);
  const [isPickerOpen, setIsPickerOpen] = useState(false);

  return (
    <View>
      {uri ? (
        <View>
          <TouchableOpacity activeOpacity={0.9} onPress={() => setIsViewing(true)}>
            <Image
              source={{uri}}
              style={{width: '100%', height: moderateScale(height), borderRadius: radius.md}}
              resizeMode="cover"
            />
          </TouchableOpacity>

          <View
            style={{
              position: 'absolute',
              top: moderateScale(8),
              right: moderateScale(8),
              flexDirection: 'row',
            }}>
            <OverlayAction
              icon="eye-outline"
              onPress={() => setIsViewing(true)}
              accessibilityLabel={`View ${label ?? 'attachment'}`}
            />
            {!!onRemove && (
              <View style={{marginLeft: moderateScale(6)}}>
                <OverlayAction
                  icon="trash-outline"
                  onPress={onRemove}
                  accessibilityLabel={`Remove ${label ?? 'attachment'}`}
                />
              </View>
            )}
          </View>
        </View>
      ) : (
        <TouchableOpacity activeOpacity={0.85} onPress={() => setIsPickerOpen(true)}>
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
        </TouchableOpacity>
      )}

      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(4)}}>
          {error}
        </AppText>
      )}

      <ImageViewer
        uri={uri}
        label={label}
        visible={isViewing}
        onClose={() => setIsViewing(false)}
      />

      <ActionSheet
        visible={isPickerOpen}
        onClose={() => setIsPickerOpen(false)}
        title={label ?? 'Attach Photo'}
        options={[
          {key: 'camera', icon: 'camera-outline', label: 'Take Photo', onPress: () => pickFromCamera(onPick, crop)},
          {key: 'library', icon: 'image-outline', label: 'Choose from Library', onPress: () => pickFromLibrary(onPick, crop)},
        ]}
      />
    </View>
  );
};

export default AttachBox;
