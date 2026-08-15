import React, {useState} from 'react';
import {Modal, Platform, Pressable, TouchableOpacity, View} from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, verticalScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Button from './Button';

const TRIGGER_HEIGHT = verticalScale(42);

/**
 * A tappable date field, shaped like Dropdown's trigger so a form reads as one control set.
 *
 * The value it owns is a `Date | null`, not a typed string. Asking someone to key
 * "DD/MM/YYYY" into a text box means every locale habit (31-12-2027, 12/31/2027,
 * 2027/12/31) becomes a validation failure on a field that cannot actually be wrong —
 * a calendar cannot produce an impossible date, so the format class of error disappears.
 *
 * iOS gets the picker in a sheet with an explicit Done: its spinner emits a change for
 * every scroll tick, so committing on change would close the sheet on the first nudge.
 * Android's dialog is already modal and returns a single confirmed value.
 */
const DateField = ({
  label,
  placeholder = 'Select a date',
  value,
  onChange,
  error,
  minimumDate,
  maximumDate,
}) => {
  const {colors, radius, spacing} = useAppTheme();
  const [isOpen, setIsOpen] = useState(false);
  // iOS edits a copy so backing out of the sheet leaves the committed value alone.
  const [draft, setDraft] = useState(null);

  const open = () => {
    setDraft(value ?? defaultFocusDate(minimumDate, maximumDate));
    setIsOpen(true);
  };

  const handleAndroidChange = (event, selected) => {
    setIsOpen(false);
    if (event.type === 'set' && selected) {
      onChange(selected);
    }
  };

  const confirm = () => {
    onChange(draft ?? defaultFocusDate(minimumDate, maximumDate));
    setIsOpen(false);
  };

  const picker = (
    <DateTimePicker
      value={
        (Platform.OS === 'ios' ? draft : value) ??
        defaultFocusDate(minimumDate, maximumDate)
      }
      mode="date"
      display={Platform.OS === 'ios' ? 'spinner' : 'default'}
      minimumDate={minimumDate}
      maximumDate={maximumDate}
      themeVariant="light"
      onChange={
        Platform.OS === 'ios'
          ? (_, selected) => selected && setDraft(selected)
          : handleAndroidChange
      }
    />
  );

  return (
    <View style={{marginBottom: spacing.sm}}>
      {label && (
        <AppText
          variant="caption"
          color={colors.textSecondary}
          style={{marginBottom: moderateScale(5)}}>
          {label}
        </AppText>
      )}

      <TouchableOpacity
        activeOpacity={0.8}
        onPress={open}
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          borderWidth: 1,
          borderColor: error
            ? colors.danger
            : isOpen
            ? colors.primary
            : colors.border,
          // Radius stays 0 — same square-corners rule as every other field.
          borderRadius: radius.sm,
          height: TRIGGER_HEIGHT,
          paddingHorizontal: spacing.sm,
          backgroundColor: colors.background,
          // Same resting/open shadow as Input, so a form of mixed field types
          // (text, dropdown, date) reads as one consistent control set.
          shadowColor: '#000',
          shadowOffset: {width: 0, height: isOpen ? 2 : 1},
          shadowOpacity: isOpen ? 0.1 : 0.05,
          shadowRadius: isOpen ? 5 : 3,
          elevation: isOpen ? 3 : 1,
        }}>
        <Icon
          name="calendar-outline"
          size={moderateScale(15)}
          color={colors.textMuted}
          style={{marginRight: moderateScale(7)}}
        />
        <AppText
          variant="body"
          color={value ? colors.textPrimary : colors.textMuted}
          numberOfLines={1}
          style={{flex: 1, fontSize: moderateScale(13.5)}}>
          {value ? formatDisplayDate(value) : placeholder}
        </AppText>
        <Icon
          name="chevron-down"
          size={moderateScale(16)}
          color={colors.textMuted}
        />
      </TouchableOpacity>

      {error && (
        <AppText
          variant="caption"
          color={colors.danger}
          style={{marginTop: moderateScale(4)}}>
          {error}
        </AppText>
      )}

      {isOpen &&
        (Platform.OS === 'ios' ? (
          <Modal
            visible
            transparent
            animationType="fade"
            onRequestClose={() => setIsOpen(false)}>
            <Pressable
              style={{
                flex: 1,
                backgroundColor: colors.overlayStrong,
                justifyContent: 'flex-end',
              }}
              onPress={() => setIsOpen(false)}>
              {/* Swallows taps so scrolling the spinner does not dismiss the sheet. */}
              <Pressable
                onPress={() => {}}
                style={{
                  backgroundColor: colors.card,
                  paddingHorizontal: spacing.lg,
                  paddingBottom: spacing.lg,
                }}>
                <View
                  style={{
                    flexDirection: 'row',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    paddingTop: spacing.md,
                    paddingBottom: spacing.xs,
                  }}>
                  <AppText variant="h3">{label ?? 'Select a date'}</AppText>
                  <TouchableOpacity
                    onPress={() => setIsOpen(false)}
                    hitSlop={10}>
                    <Icon
                      name="close"
                      size={moderateScale(22)}
                      color={colors.textPrimary}
                    />
                  </TouchableOpacity>
                </View>
                {picker}
                <Button label="Done" onPress={confirm} />
              </Pressable>
            </Pressable>
          </Modal>
        ) : (
          picker
        ))}
    </View>
  );
};

/** Where the picker opens when nothing is chosen yet — clamped into the allowed range. */
function defaultFocusDate(minimumDate, maximumDate) {
  const today = new Date();
  if (minimumDate && today < minimumDate) {
    return minimumDate;
  }
  if (maximumDate && today > maximumDate) {
    return maximumDate;
  }
  return today;
}

/** DD/MM/YYYY — what the form asked people to type, now only ever rendered, never parsed. */
export function formatDisplayDate(date) {
  const pad = n => String(n).padStart(2, '0');
  return `${pad(date.getDate())}/${pad(
    date.getMonth() + 1,
  )}/${date.getFullYear()}`;
}

/**
 * "20 September 2022" — the one long-form date reading used across both apps
 * (RERA certificate expiry, etc.). Takes the API's raw ISO string directly
 * (a record with no expiry has no field to show at all, so callers already
 * guard on that before reaching here) rather than a parsed `Date`, since every
 * call site is a straight passthrough from a profile/broker API response.
 */
export function formatLongDate(iso) {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  return Number.isNaN(date.getTime())
    ? null
    : date.toLocaleDateString('en-GB', {day: 'numeric', month: 'long', year: 'numeric'});
}

/**
 * YYYY-MM-DD for the API. Built from the local calendar fields rather than toISOString(),
 * which converts to UTC first and so reports the previous day for anyone east of GMT —
 * the whole user base here.
 */
export function toApiDate(date) {
  const pad = n => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
    date.getDate(),
  )}`;
}

export default DateField;
