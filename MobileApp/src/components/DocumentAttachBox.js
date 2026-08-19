import React, {useState} from 'react';
import {ActivityIndicator, Alert, TouchableOpacity, View} from 'react-native';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import DocumentPicker, {isCancel as isDocumentPickCancelled} from 'react-native-document-picker';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import ActionSheet from './ActionSheet';
import AppText from './AppText';

/**
 * Same shape AttachBox's own camera/library options use — a document scan does
 * not need to be any bigger than this to stay legible.
 */
const PHOTO_PICKER_OPTIONS = {mediaType: 'photo', quality: 0.7, maxWidth: 1600, maxHeight: 1600};

function pickFromCamera(onPick, label) {
  launchCamera(PHOTO_PICKER_OPTIONS, response => {
    // Cancelling is a normal outcome, not a failure.
    if (response.didCancel) {
      return;
    }

    const asset = response?.assets?.[0];
    if (asset?.uri) {
      // Not `asset.fileName` — for a camera capture that's the library's own
      // internal temp file name (e.g. "rn_image_picker_lib_temp_8f3a2c1d.jpg"),
      // not anything meaningful to show a broker. A gallery pick's fileName is
      // the real file on disk, which is worth keeping — see pickFromLibrary.
      onPick({uri: asset.uri, name: label ? `${label}.jpg` : 'photo.jpg', type: asset.type || 'image/jpeg'});
      return;
    }

    // No uri and not a cancel means the camera itself couldn't be reached — most
    // commonly the Simulator, which has no camera hardware at all.
    Alert.alert(
      'Camera unavailable',
      response.errorMessage ?? 'Could not open the camera on this device. Try Choose from Library instead.',
    );
  });
}

function pickFromLibrary(onPick) {
  launchImageLibrary(PHOTO_PICKER_OPTIONS, response => {
    const asset = response?.assets?.[0];
    if (asset?.uri) {
      onPick({uri: asset.uri, name: asset.fileName || 'photo.jpg', type: asset.type || 'image/jpeg'});
    }
  });
}

async function pickPdf(onPick) {
  let picked;
  try {
    picked = await DocumentPicker.pickSingle({type: [DocumentPicker.types.pdf]});
  } catch (error) {
    if (!isDocumentPickCancelled(error)) {
      Alert.alert('Could not open file picker', error?.message ?? 'Please try again.');
    }
    return;
  }

  onPick({uri: picked.uri, name: picked.name, type: picked.type || 'application/pdf'});
}

/**
 * A PDF-or-photo document attachment — for KYC documents (PAN, Aadhaar, RERA, GST)
 * that can arrive any of three ways. Same bottom sheet `AttachBox` offers (Take
 * Photo / Choose from Library), plus a third "Choose PDF" option alongside them —
 * unlike `AttachBox` itself, which is camera/gallery-only and right for something
 * like the profile photo, where a PDF makes no sense.
 *
 * `value` is either `null`, or `{uri, name, type}` — a locally-picked file, or a
 * previously-saved attachment resuming from a draft (its `uri` a full https:// URL
 * in that case, which the caller's own `isRemoteUri()` check is what stops it from
 * ever being re-uploaded as if it were fresh).
 *
 * `loading` covers the moment between a fresh pick and the caller's own upload
 * call (see CompleteProfileScreen's `handleAttachmentPick`) resolving — the picker
 * is closed off during it so a second tap can't start a second upload for the
 * same slot while the first is still in flight.
 */
const DocumentAttachBox = ({value, onPick, onRemove, label, placeholder, error, loading = false}) => {
  const {colors, radius, spacing} = useAppTheme();
  const [isPickerOpen, setIsPickerOpen] = useState(false);

  return (
    <View>
      <TouchableOpacity
        activeOpacity={0.85}
        disabled={loading}
        onPress={() => setIsPickerOpen(true)}
        style={{marginBottom: spacing.xs}}>
        {loading ? (
          <View
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              justifyContent: 'center',
              borderWidth: 1.5,
              borderStyle: 'dashed',
              borderColor: colors.primary,
              borderRadius: radius.md,
              paddingVertical: spacing.md,
              backgroundColor: colors.primarySoft,
            }}>
            <ActivityIndicator size="small" color={colors.primaryDark} />
            <AppText variant="caption" color={colors.primaryDark} style={{marginLeft: spacing.xs}}>
              Uploading…
            </AppText>
          </View>
        ) : value ? (
          <View
            style={{
              flexDirection: 'row',
              alignItems: 'center',
              borderWidth: 1,
              borderColor: colors.border,
              borderRadius: radius.md,
              paddingVertical: spacing.sm,
              paddingHorizontal: spacing.sm,
              backgroundColor: colors.background,
            }}>
            <Icon name="document-text-outline" size={moderateScale(20)} color={colors.primaryDark} />
            <AppText
              variant="caption"
              color={colors.textPrimary}
              numberOfLines={1}
              style={{flex: 1, marginLeft: spacing.xs}}>
              {value.name}
            </AppText>
            {!!onRemove && (
              <TouchableOpacity onPress={onRemove} hitSlop={8}>
                <Icon name="close-circle" size={moderateScale(18)} color={colors.textMuted} />
              </TouchableOpacity>
            )}
          </View>
        ) : (
          <View
            style={{
              borderWidth: 1.5,
              borderStyle: 'dashed',
              borderColor: error ? colors.danger : colors.primary,
              borderRadius: radius.md,
              paddingVertical: spacing.md,
              alignItems: 'center',
              justifyContent: 'center',
              backgroundColor: colors.primarySoft,
            }}>
            <Icon name="document-attach-outline" size={moderateScale(20)} color={colors.primaryDark} />
            <AppText variant="caption" color={colors.primaryDark} style={{marginTop: moderateScale(4)}}>
              {placeholder ?? 'Tap to attach — PDF or a photo'}
            </AppText>
          </View>
        )}
      </TouchableOpacity>
      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(-2), marginBottom: spacing.xs}}>
          {error}
        </AppText>
      )}

      <ActionSheet
        visible={isPickerOpen}
        onClose={() => setIsPickerOpen(false)}
        title={label ? `Attach ${label}` : 'Attach document'}
        options={[
          {key: 'camera', icon: 'camera-outline', label: 'Take Photo', onPress: () => pickFromCamera(onPick, label)},
          {key: 'library', icon: 'image-outline', label: 'Choose from Library', onPress: () => pickFromLibrary(onPick)},
          {key: 'pdf', icon: 'document-outline', label: 'Choose PDF', onPress: () => pickPdf(onPick)},
        ]}
      />
    </View>
  );
};

export default DocumentAttachBox;
