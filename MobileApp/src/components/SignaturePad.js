import React, {useMemo, useRef, useState} from 'react';
import {PanResponder, TouchableOpacity, View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const SignaturePad = ({onChange, error, height = 130}) => {
  const {colors, radius} = useAppTheme();
  const [strokes, setStrokes] = useState([]);
  const currentStroke = useRef([]);

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onMoveShouldSetPanResponder: () => true,
        onPanResponderGrant: e => {
          const point = {x: e.nativeEvent.locationX, y: e.nativeEvent.locationY};
          currentStroke.current = [point];
          setStrokes(prev => [...prev, [point]]);
        },
        onPanResponderMove: e => {
          const point = {x: e.nativeEvent.locationX, y: e.nativeEvent.locationY};
          currentStroke.current = [...currentStroke.current, point];
          setStrokes(prev => {
            const next = prev.slice();
            next[next.length - 1] = currentStroke.current;
            return next;
          });
        },
        onPanResponderRelease: () => {
          onChange?.(true);
          currentStroke.current = [];
        },
      }),
    [onChange],
  );

  const handleClear = () => {
    setStrokes([]);
    onChange?.(false);
  };

  const points = strokes.flat();

  return (
    <View>
      <View
        {...panResponder.panHandlers}
        style={{
          height: moderateScale(height),
          borderWidth: 1.5,
          borderStyle: 'dashed',
          borderColor: error ? colors.danger : colors.border,
          borderRadius: radius.md,
          backgroundColor: colors.background,
          overflow: 'hidden',
        }}>
        {points.length === 0 && (
          <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
            <AppText variant="caption" color={colors.textMuted}>
              Sign here
            </AppText>
          </View>
        )}
        {points.map((p, i) => (
          <View
            key={i}
            style={{
              position: 'absolute',
              left: p.x - 1.5,
              top: p.y - 1.5,
              width: 3,
              height: 3,
              borderRadius: 1.5,
              backgroundColor: colors.textPrimary,
            }}
          />
        ))}
      </View>
      <View style={{flexDirection: 'row', justifyContent: 'space-between', marginTop: moderateScale(4)}}>
        <AppText variant="caption" color={error ? colors.danger : colors.textMuted}>
          {error ?? ' '}
        </AppText>
        {points.length > 0 && (
          <TouchableOpacity onPress={handleClear} hitSlop={8}>
            <AppText variant="captionMedium" color={colors.primary}>
              Clear
            </AppText>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
};

export default SignaturePad;
