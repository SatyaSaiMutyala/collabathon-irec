import React, {useState} from 'react';
import {ActivityIndicator, Alert, Modal, Pressable, TouchableOpacity, View} from 'react-native';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import DocumentPicker, {isCancel as isDocumentPickCancelled} from 'react-native-document-picker';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import ActionSheet from './ActionSheet';
import AppText from './AppText';
import RemoteImage from './RemoteImage';

/**
 * Same shape AttachBox's own camera/library options use — a document scan does
 * not need to be any bigger than this to stay legible.
 */
const PHOTO_PICKER_OPTIONS = {mediaType: 'photo', quality: 0.7, maxWidth: 1600, maxHeight: 1600};

/** Whether `value` is something an <Image> can actually render — a PDF can't be. */
const isImageAttachment = value => /\.(jpe?g|png|webp|gif)$/i.test(value?.uri ?? value?.name ?? '');

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
 * Full-screen look at an attached document photo — same shape and reasoning as
 * AttachField's own ImageViewer (not shared from there since that one isn't
 * exported, and this one has no crop/avatar context to carry).
 */
const ImageViewer = ({uri, label, visible, onClose}) => {
  const {colors, spacing} = useAppTheme();

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent
      navigationBarTranslucent>
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

        <RemoteImage uri={uri} style={{flex: 1, width: '100%'}} resizeMode="contain" />

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
 * A filled photo (the common case — PAN/RERA/GST/Aadhaar are usually camera scans,
 * not PDFs) shows an actual thumbnail with a tap-to-view, same as AttachBox's own
 * profile photo does — not just a filename chip, which said nothing about what was
 * actually on file for a resumed or reopened registration. A PDF has no easy inline
 * preview, so it keeps the filename-chip treatment; tapping it re-opens the picker
 * to replace, same as before this changed.
 *
 * `loading` covers the moment between a fresh pick and the caller's own upload
 * call (see CompleteProfileScreen's `handleAttachmentPick`) resolving — the picker
 * is closed off during it so a second tap can't start a second upload for the
 * same slot while the first is still in flight.
 */
const DocumentAttachBox = ({value, onPick, onRemove, label, placeholder, error, loading = false}) => {
  const {colors, radius, spacing} = useAppTheme();
  const [isPickerOpen, setIsPickerOpen] = useState(false);
  const [isViewing, setIsViewing] = useState(false);
  const isImage = isImageAttachment(value);

  return (
    <View>
      {!loading && value && isImage ? (
        <View style={{marginBottom: spacing.xs}}>
          <TouchableOpacity activeOpacity={0.9} onPress={() => setIsViewing(true)}>
            <RemoteImage
              uri={value.uri}
              style={{width: '100%', height: moderateScale(110), borderRadius: radius.md}}
              resizeMode="cover"
            />
          </TouchableOpacity>

          <View style={{position: 'absolute', top: moderateScale(6), right: moderateScale(6), flexDirection: 'row'}}>
            <TouchableOpacity
              activeOpacity={0.8}
              onPress={() => setIsViewing(true)}
              hitSlop={6}
              accessibilityLabel={`View ${label ?? 'attachment'}`}
              style={{
                width: moderateScale(26),
                height: moderateScale(26),
                backgroundColor: colors.overlayStrong,
                alignItems: 'center',
                justifyContent: 'center',
              }}>
              <Icon name="eye-outline" size={moderateScale(14)} color={colors.white} />
            </TouchableOpacity>
            {!!onRemove && (
              <TouchableOpacity
                activeOpacity={0.8}
                onPress={onRemove}
                hitSlop={6}
                accessibilityLabel={`Remove ${label ?? 'attachment'}`}
                style={{
                  width: moderateScale(26),
                  height: moderateScale(26),
                  backgroundColor: colors.overlayStrong,
                  alignItems: 'center',
                  justifyContent: 'center',
                  marginLeft: moderateScale(6),
                }}>
                <Icon name="trash-outline" size={moderateScale(14)} color={colors.white} />
              </TouchableOpacity>
            )}
          </View>
        </View>
      ) : (
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
      )}

      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(-2), marginBottom: spacing.xs}}>
          {error}
        </AppText>
      )}

      <ImageViewer uri={value?.uri} label={label} visible={isViewing} onClose={() => setIsViewing(false)} />

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
