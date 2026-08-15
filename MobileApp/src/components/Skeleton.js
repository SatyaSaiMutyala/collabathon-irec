import React, {useEffect, useRef, useState} from 'react';
import {Animated, Easing, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';

/**
 * One shimmering placeholder block — the primitive every composed skeleton is built from.
 *
 * A sweeping highlight rather than a pulsing opacity: a pulse reads as "this thing is
 * disabled", a sweep reads as "this thing is arriving". The sweep is one Animated value
 * driven on the native thread, so a screen full of these still costs one JS-side loop
 * per block and nothing per frame.
 *
 * Sizes are responsive by default — `width` takes a percentage string as happily as a
 * number, so a skeleton laid out inside a flex row matches whatever the real content
 * will occupy rather than a hardcoded guess.
 */
const Skeleton = ({
  width = '100%',
  height = moderateScale(12),
  radius = 0,
  style,
}) => {
  const {colors} = useAppTheme();
  const progress = useRef(new Animated.Value(0)).current;
  // The native driver only accepts numeric transform values, never percentage strings —
  // so the sweep's travel distance has to come from the block's actual measured width.
  const [measuredWidth, setMeasuredWidth] = useState(0);

  useEffect(() => {
    const loop = Animated.loop(
      Animated.timing(progress, {
        toValue: 1,
        duration: 1200,
        easing: Easing.inOut(Easing.ease),
        useNativeDriver: true,
      }),
    );
    loop.start();

    // Without this a skeleton left mounted behind a navigation push keeps animating.
    return () => loop.stop();
  }, [progress]);

  // Travels a full width beyond each edge so the highlight enters and leaves cleanly
  // instead of appearing mid-block.
  const translateX = progress.interpolate({
    inputRange: [0, 1],
    outputRange: [-measuredWidth, measuredWidth],
  });

  return (
    <View
      onLayout={event => setMeasuredWidth(event.nativeEvent.layout.width)}
      style={[
        {width, height, borderRadius: radius, backgroundColor: colors.surface, overflow: 'hidden'},
        style,
      ]}>
      <Animated.View style={[{width: '100%', height: '100%'}, {transform: [{translateX}]}]}>
        <LinearGradient
          colors={[colors.surface, colors.border, colors.surface]}
          start={{x: 0, y: 0}}
          end={{x: 1, y: 0}}
          style={{width: '100%', height: '100%'}}
        />
      </Animated.View>
    </View>
  );
};

export default Skeleton;
