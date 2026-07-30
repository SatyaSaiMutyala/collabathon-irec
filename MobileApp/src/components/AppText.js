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
  const {colors, textVariants, fontFamily, fontWeight} = useAppTheme();
  const base = textVariants[variant];

  return (
    <Text
      {...rest}
      style={[
        {
          // One family for every style; `weight` now overrides the numeric weight
          // rather than swapping in a differently-named font file.
          fontFamily: weight ? fontFamily[weight] : base.fontFamily,
          fontWeight: weight ? fontWeight[weight] : base.fontWeight,
          fontSize: base.fontSize,
          lineHeight: base.lineHeight,
          letterSpacing: base.letterSpacing,
          color: color ?? colors.textPrimary,
          textAlign: align,
          includeFontPadding: false,
        },
        style,
      ]}>
      {children}
    </Text>
  );
};

export default AppText;
