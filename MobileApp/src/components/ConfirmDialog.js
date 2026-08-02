import React from 'react';
import {Modal, Pressable, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Button from './Button';

/**
 * A confirmation the caller owns — `visible` in, `onConfirm`/`onCancel` out.
 *
 * Controlled rather than store-driven on purpose, unlike Snackbar. A snackbar is fired
 * and forgotten from anywhere; a confirmation has to hand an answer back to the exact
 * caller that asked, and routing that through the store would mean every caller
 * inventing an id to tell its own answer apart from someone else's.
 *
 * Tapping the backdrop cancels, same as Cancel — a confirmation should never be harder
 * to escape than to accept.
 */
const ConfirmDialog = ({
  visible,
  title,
  message,
  icon = 'help-circle-outline',
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  tone = 'default', // 'default' | 'danger'
  busy = false,
  onConfirm,
  onCancel,
}) => {
  const {colors, radius, spacing} = useAppTheme();

  const accent = tone === 'danger' ? colors.danger : colors.primary;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <Pressable
        style={[styles.backdrop, {backgroundColor: colors.overlayStrong}]}
        onPress={busy ? undefined : onCancel}>
        {/* Swallows the press so a tap inside the card does not read as a backdrop tap. */}
        <Pressable
          onPress={event => event.stopPropagation()}
          style={[
            styles.card,
            {
              backgroundColor: colors.card,
              borderRadius: radius.lg,
              padding: spacing.lg,
            },
          ]}>
          <View style={{alignItems: 'center'}}>
            <Icon name={icon} size={moderateScale(34)} color={accent} />
            <AppText variant="h3" align="center" style={{marginTop: spacing.sm}}>
              {title}
            </AppText>
            {!!message && (
              <AppText
                variant="body"
                color={colors.textSecondary}
                align="center"
                style={{marginTop: spacing.xs}}>
                {message}
              </AppText>
            )}
          </View>

          <View style={{flexDirection: 'row', marginTop: spacing.lg}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Button
                label={cancelLabel}
                variant="outline"
                size="md"
                disabled={busy}
                onPress={onCancel}
              />
            </View>
            <View style={{flex: 1, marginLeft: spacing.xs}}>
              <Button
                label={busy ? 'Please wait…' : confirmLabel}
                size="md"
                disabled={busy}
                onPress={onConfirm}
                style={tone === 'danger' ? {backgroundColor: colors.danger} : undefined}
              />
            </View>
          </View>
        </Pressable>
      </Pressable>
    </Modal>
  );
};

const styles = {
  backdrop: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: moderateScale(24),
  },
  card: {
    width: '100%',
    maxWidth: moderateScale(360),
  },
};

export default ConfirmDialog;
