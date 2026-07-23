import React, {useRef, useState} from 'react';
import {Modal, Pressable, ScrollView, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, verticalScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const TRIGGER_HEIGHT = verticalScale(42);
const ITEM_HEIGHT = verticalScale(44);
const MAX_VISIBLE_ITEMS = 6;

const Dropdown = ({label, placeholder, displayValue, options, multiSelect = false, selected, onSelectSingle, onToggleMulti, error}) => {
  const {colors, radius, spacing} = useAppTheme();
  const [isOpen, setIsOpen] = useState(false);
  const [layout, setLayout] = useState(null);
  const triggerRef = useRef(null);

  const isSelected = option => (multiSelect ? selected.includes(option) : selected === option);

  const openDropdown = () => {
    triggerRef.current?.measureInWindow((x, y, width, height) => {
      setLayout({x, y, width, height});
      setIsOpen(true);
    });
  };

  const handlePress = option => {
    if (multiSelect) {
      onToggleMulti(option);
    } else {
      onSelectSingle(option);
      setIsOpen(false);
    }
  };

  return (
    <View style={{marginBottom: spacing.sm}}>
      {label && (
        <AppText variant="caption" color={colors.textSecondary} style={{marginBottom: moderateScale(5)}}>
          {label}
        </AppText>
      )}

      <TouchableOpacity
        ref={triggerRef}
        activeOpacity={0.8}
        onPress={() => (isOpen ? setIsOpen(false) : openDropdown())}
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          borderWidth: 1,
          borderColor: error ? colors.danger : isOpen ? colors.primary : colors.border,
          borderRadius: radius.sm,
          height: TRIGGER_HEIGHT,
          paddingHorizontal: spacing.sm,
          backgroundColor: colors.background,
        }}>
        <AppText
          variant="body"
          color={displayValue ? colors.textPrimary : colors.textMuted}
          numberOfLines={1}
          style={{flex: 1, fontSize: moderateScale(13.5)}}>
          {displayValue || placeholder}
        </AppText>
        <Icon name={isOpen ? 'chevron-up' : 'chevron-down'} size={moderateScale(16)} color={colors.textMuted} />
      </TouchableOpacity>

      {error && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: moderateScale(4)}}>
          {error}
        </AppText>
      )}

      {isOpen && layout && (
        <Modal
          visible
          transparent
          animationType="fade"
          statusBarTranslucent
          onRequestClose={() => setIsOpen(false)}>
          <Pressable style={{flex: 1}} onPress={() => setIsOpen(false)}>
            <View
              style={{
                position: 'absolute',
                top: layout.y + layout.height + moderateScale(4),
                left: layout.x,
                width: layout.width,
                borderWidth: 1,
                borderColor: colors.primary,
                borderRadius: radius.sm,
                backgroundColor: colors.background,
                overflow: 'hidden',
                shadowColor: '#12141C',
                shadowOffset: {width: 0, height: 6},
                shadowOpacity: 0.2,
                shadowRadius: 16,
                elevation: 12,
              }}>
              <ScrollView
                nestedScrollEnabled
                showsVerticalScrollIndicator={options.length > MAX_VISIBLE_ITEMS}
                style={
                  options.length > MAX_VISIBLE_ITEMS
                    ? {maxHeight: ITEM_HEIGHT * MAX_VISIBLE_ITEMS}
                    : undefined
                }>
                {options.map((option, index) => (
                  <TouchableOpacity
                    key={option}
                    activeOpacity={0.75}
                    onPress={() => handlePress(option)}
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      height: ITEM_HEIGHT,
                      paddingHorizontal: spacing.sm,
                      borderTopWidth: index === 0 ? 0 : 1,
                      borderTopColor: colors.border,
                      backgroundColor: isSelected(option) ? colors.primarySoft : colors.background,
                    }}>
                    <AppText variant="body" color={isSelected(option) ? colors.primaryDark : colors.textPrimary}>
                      {option}
                    </AppText>
                    {isSelected(option) && (
                      <Icon name="checkmark" size={moderateScale(15)} color={colors.primary} />
                    )}
                  </TouchableOpacity>
                ))}
              </ScrollView>
            </View>
          </Pressable>
        </Modal>
      )}
    </View>
  );
};

export default Dropdown;
