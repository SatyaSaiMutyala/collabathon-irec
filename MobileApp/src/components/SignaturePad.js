import React, {useMemo, useRef, useState} from 'react';
import {PanResponder, TouchableOpacity, View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const SignaturePad = ({onChange, onDrawStart, onDrawEnd, error, height = 130}) => {
  const {colors, radius} = useAppTheme();
  const [strokes, setStrokes] = useState([]);
  const currentStroke = useRef([]);

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onMoveShouldSetPanResponder: () => true,
        onStartShouldSetPanResponderCapture: () => true,
        onMoveShouldSetPanResponderCapture: () => true,
        onPanResponderTerminationRequest: () => false,
        onPanResponderGrant: e => {
          onDrawStart?.();
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
          onDrawEnd?.();
        },
        onPanResponderTerminate: () => {
          currentStroke.current = [];
          onDrawEnd?.();
        },
      }),
    [onChange, onDrawStart, onDrawEnd],
  );

  const handleClear = () => {
    setStrokes([]);
    onChange?.(false);
  };

  const points = strokes.flat();
  const LINE_WIDTH = 2.5;

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
        {strokes.map((stroke, strokeIndex) => (
          <React.Fragment key={strokeIndex}>
            {stroke.map((p, i) => (
              <View
                key={`dot-${i}`}
                style={{
                  position: 'absolute',
                  left: p.x - LINE_WIDTH / 2,
                  top: p.y - LINE_WIDTH / 2,
                  width: LINE_WIDTH,
                  height: LINE_WIDTH,
                  borderRadius: LINE_WIDTH / 2,
                  backgroundColor: colors.textPrimary,
                }}
              />
            ))}
            {stroke.slice(1).map((p, i) => {
              const prev = stroke[i];
              const dx = p.x - prev.x;
              const dy = p.y - prev.y;
              const length = Math.sqrt(dx * dx + dy * dy);
              const angle = (Math.atan2(dy, dx) * 180) / Math.PI;
              return (
                <View
                  key={`seg-${i}`}
                  style={{
                    position: 'absolute',
                    left: (prev.x + p.x) / 2 - length / 2,
                    top: (prev.y + p.y) / 2 - LINE_WIDTH / 2,
                    width: length,
                    height: LINE_WIDTH,
                    borderRadius: LINE_WIDTH / 2,
                    backgroundColor: colors.textPrimary,
                    transform: [{rotate: `${angle}deg`}],
                  }}
                />
              );
            })}
          </React.Fragment>
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
