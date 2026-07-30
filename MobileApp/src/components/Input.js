import React, {useState} from 'react';
import {StyleSheet, TextInput, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale, verticalScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const Input = ({label, error, leftIcon, isPassword = false, multiline = false, style, ...rest}) => {
  const {colors, radius, spacing, fontFamily, fontWeight} = useAppTheme();
  const [isFocused, setIsFocused] = useState(false);
  const [secure, setSecure] = useState(isPassword);

  const borderColor = error ? colors.danger : isFocused ? colors.primary : colors.border;

  return (
    <View style={{marginBottom: spacing.sm}}>
      {label && (
        <AppText variant="caption" color={colors.textSecondary} style={styles.label}>
          {label}
        </AppText>
      )}
      <View
        style={[
          styles.container,
          {
            borderColor,
            borderRadius: radius.sm,
            backgroundColor: colors.background,
            paddingHorizontal: spacing.sm,
          },
          multiline && styles.containerMultiline,
        ]}>
        {leftIcon && (
          <Icon
            name={leftIcon}
            size={moderateScale(15)}
            color={colors.textMuted}
            style={[styles.leftIcon, multiline && styles.leftIconMultiline]}
          />
        )}
        <TextInput
          {...rest}
          multiline={multiline}
          textAlignVertical={multiline ? 'top' : 'center'}
          secureTextEntry={secure}
          placeholderTextColor={colors.textMuted}
          onFocus={e => {
            setIsFocused(true);
            rest.onFocus?.(e);
          }}
          onBlur={e => {
            setIsFocused(false);
            rest.onBlur?.(e);
          }}
          style={[
            styles.input,
            multiline && styles.inputMultiline,
            {
              color: colors.textPrimary,
              fontFamily: fontFamily.regular,
              fontWeight: fontWeight.regular,
              fontSize: moderateScale(13.5),
              letterSpacing: 0,
            },
            style,
          ]}
        />
        {isPassword && (
          <TouchableOpacity onPress={() => setSecure(s => !s)} hitSlop={8}>
            <Icon
              name={secure ? 'eye-off-outline' : 'eye-outline'}
              size={moderateScale(15)}
              color={colors.textMuted}
            />
          </TouchableOpacity>
        )}
      </View>
      {error && (
        <AppText variant="caption" color={colors.danger} style={styles.error}>
          {error}
        </AppText>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  label: {
    marginBottom: moderateScale(5),
  },
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    height: verticalScale(42),
  },
  containerMultiline: {
    height: undefined,
    minHeight: verticalScale(84),
    alignItems: 'flex-start',
    paddingVertical: moderateScale(10),
  },
  leftIcon: {
    marginRight: moderateScale(7),
  },
  leftIconMultiline: {
    marginTop: moderateScale(2),
  },
  input: {
    flex: 1,
    height: '100%',
  },
  inputMultiline: {
    height: undefined,
    flex: 1,
  },
  error: {
    marginTop: moderateScale(4),
  },
});

export default Input;
