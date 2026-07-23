import React from 'react';
import {StyleSheet, View} from 'react-native';
import {useAppTheme} from '../theme';

const Card = ({padded = true, elevated = true, style, children, ...rest}) => {
  const {colors, radius, spacing} = useAppTheme();

  return (
    <View
      {...rest}
      style={[
        styles.base,
        {
          backgroundColor: colors.card,
          borderRadius: radius.lg,
          padding: padded ? spacing.md : 0,
          borderColor: colors.border,
        },
        elevated && styles.elevated,
        style,
      ]}>
      {children}
    </View>
  );
};

const styles = StyleSheet.create({
  base: {
    borderWidth: StyleSheet.hairlineWidth,
  },
  elevated: {
    shadowColor: '#12141C',
    shadowOffset: {width: 0, height: 6},
    shadowOpacity: 0.06,
    shadowRadius: 14,
    elevation: 3,
  },
});

export default Card;
