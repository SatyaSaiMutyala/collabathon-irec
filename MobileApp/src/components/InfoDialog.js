import React from 'react';
import {Modal, Pressable, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Button from './Button';

/**
 * A one-button heads-up — `visible` in, `onDismiss` out. Same card/backdrop language as
 * ConfirmDialog, but for telling someone something rather than asking them to decide
 * between two things: a single centered "OK" that just closes it, nothing to weigh.
 *
 * Tapping the backdrop dismisses too, same reasoning as ConfirmDialog — there's nothing
 * here worth trapping someone behind.
 */
const InfoDialog = ({
  visible,
  title,
  message,
  icon = 'checkmark-circle-outline',
  dismissLabel = 'OK',
  onDismiss,
}) => {
  const {colors, radius, spacing} = useAppTheme();

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onDismiss}
      statusBarTranslucent
      navigationBarTranslucent>
      <Pressable
        style={[styles.backdrop, {backgroundColor: colors.overlayStrong}]}
        onPress={onDismiss}>
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
            <Icon name={icon} size={moderateScale(34)} color={colors.primary} />
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

          <View style={{marginTop: spacing.lg}}>
            <Button label={dismissLabel} size="md" onPress={onDismiss} />
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

export default InfoDialog;
