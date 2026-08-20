import React from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {roundedRadius, useAppTheme} from '../theme';
import AppText from './AppText';
import Button from './Button';

/**
 * What a list shows when it has nothing to show.
 *
 * The icon is the point: a bare line of text in the middle of a blank screen reads as
 * something that failed to load. A marker above it makes the emptiness look deliberate,
 * and gives the eye somewhere to land before it reads the sentence.
 *
 * The disc is rounded — one of the counted exceptions in `roundedRadius` — because it is
 * a marker floating on the page rather than a panel; squared, it becomes an empty tile
 * sitting behind the glyph.
 */
const EmptyState = ({
  icon = 'file-tray-outline',
  title = 'Nothing here yet',
  message,
  /** Distinguishes "you have no data" from "your filters matched nothing". */
  filtered = false,
  /** Both required together — a retry/recovery action, e.g. "Try again" on a
   *  network error. Optional: most empty states (a genuinely empty list) have
   *  nothing to retry, so this stays unset there. */
  actionLabel,
  onAction,
  /** Overrides the default top margin — pass `{marginTop: 0}` when a parent is
   *  already vertically centering this (e.g. `justifyContent: 'center'`); the
   *  default 48 is tuned for the more common case of sitting docked under a
   *  header/search bar with no centering of its own. */
  style,
}) => {
  const {colors, spacing} = useAppTheme();
  const glyph = filtered ? 'search-outline' : icon;

  return (
    <View
      style={[
        {alignItems: 'center', paddingHorizontal: spacing.lg, marginTop: moderateScale(48)},
        style,
      ]}>
      <View
        style={{
          width: moderateScale(64),
          height: moderateScale(64),
          borderRadius: roundedRadius.statusIcon,
          backgroundColor: colors.surface,
          alignItems: 'center',
          justifyContent: 'center',
          marginBottom: spacing.md,
        }}>
        <Icon name={glyph} size={moderateScale(28)} color={colors.textMuted} />
      </View>

      <AppText variant="h3" align="center" style={{marginBottom: moderateScale(4)}}>
        {title}
      </AppText>

      {!!message && (
        <AppText variant="body" color={colors.textMuted} align="center">
          {message}
        </AppText>
      )}

      {!!actionLabel && !!onAction && (
        <Button
          label={actionLabel}
          variant="outline"
          onPress={onAction}
          style={{marginTop: spacing.lg, alignSelf: 'stretch'}}
        />
      )}
    </View>
  );
};

export default EmptyState;
