import React from 'react';
import {Text} from 'react-native';
import {useAppTheme} from '../theme';

const AppText = ({
  variant = 'body',
  color,
  align,
  weight,
  style,
  children,
  ...rest
}) => {
  const {colors, textVariants, fontFamily} = useAppTheme();
  const base = textVariants[variant];

  return (
    <Text
      {...rest}
      style={[
        {
          fontFamily: weight ? fontFamily[weight] : base.fontFamily,
          fontSize: base.fontSize,
          lineHeight: base.lineHeight,
          letterSpacing: base.letterSpacing,
          color: color ?? colors.textPrimary,
          textAlign: align,
        },
        style,
      ]}>
      {children}
    </Text>
  );
};

export default AppText;
