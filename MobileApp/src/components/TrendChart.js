import React, {useState} from 'react';
import {StyleSheet, View} from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const FILL_STEPS = 48;
const AXIS_WIDTH = moderateScale(28);

function interpolateY(points, x) {
  if (points.length === 1) {
    return points[0].y;
  }
  if (x <= points[0].x) {
    return points[0].y;
  }
  const last = points[points.length - 1];
  if (x >= last.x) {
    return last.y;
  }
  for (let i = 0; i < points.length - 1; i += 1) {
    const a = points[i];
    const b = points[i + 1];
    if (x >= a.x && x <= b.x) {
      const t = (x - a.x) / (b.x - a.x || 1);
      return a.y + (b.y - a.y) * t;
    }
  }
  return last.y;
}

const TrendChart = ({data, labels, height = 140}) => {
  const {colors, withAlpha} = useAppTheme();
  const [width, setWidth] = useState(0);
  const chartHeight = moderateScale(height);
  const max = Math.max(...data, 1);
  const topPad = moderateScale(24);
  const usableHeight = chartHeight - topPad;
  const dotSize = moderateScale(7);
  const lineWidth = moderateScale(2.5);

  const points = width
    ? data.map((value, index) => ({
        x: data.length > 1 ? (index / (data.length - 1)) * width : width / 2,
        y: topPad + usableHeight - (value / max) * usableHeight,
        value,
      }))
    : [];

  const lastPoint = points[points.length - 1];

  const fillColumns = [];
  if (width > 0 && points.length > 0) {
    const colWidth = width / FILL_STEPS;
    for (let i = 0; i < FILL_STEPS; i += 1) {
      const x = i * colWidth;
      const y = interpolateY(points, x + colWidth / 2);
      fillColumns.push({x, y, colHeight: Math.max(chartHeight - y, moderateScale(1))});
    }
  }

  const axisTicks = [
    {value: max, y: topPad},
    {value: Math.round(max / 2), y: topPad + usableHeight / 2},
    {value: 0, y: topPad + usableHeight},
  ];

  return (
    <View>
      <View style={{flexDirection: 'row'}}>
        <View style={{width: AXIS_WIDTH, height: chartHeight}}>
          {axisTicks.map(tick => (
            <AppText
              key={tick.value}
              variant="overline"
              color={colors.textMuted}
              style={{position: 'absolute', top: Math.min(Math.max(tick.y - moderateScale(6), 0), chartHeight - moderateScale(12))}}>
              {tick.value}
            </AppText>
          ))}
        </View>

        <View style={{flex: 1, height: chartHeight}} onLayout={e => setWidth(e.nativeEvent.layout.width)}>
        {axisTicks.map(tick => (
          <View
            key={`grid-${tick.value}`}
            style={{
              position: 'absolute',
              left: 0,
              right: 0,
              top: tick.y,
              height: StyleSheet.hairlineWidth,
              backgroundColor: colors.border,
            }}
          />
        ))}

        {fillColumns.map((col, index) => (
          <LinearGradient
            key={`fill-${index}`}
            colors={[withAlpha(colors.primary, 0.32), withAlpha(colors.primary, 0)]}
            style={{
              position: 'absolute',
              left: col.x,
              top: col.y,
              width: width / FILL_STEPS + 1,
              height: col.colHeight,
            }}
          />
        ))}

        {points.length > 1 &&
          points.slice(0, -1).map((point, index) => {
            const next = points[index + 1];
            const dx = next.x - point.x;
            const dy = next.y - point.y;
            const length = Math.sqrt(dx * dx + dy * dy);
            const angle = (Math.atan2(dy, dx) * 180) / Math.PI;
            const midX = (point.x + next.x) / 2;
            const midY = (point.y + next.y) / 2;
            return (
              <View
                key={index}
                style={{
                  position: 'absolute',
                  left: midX - length / 2,
                  top: midY - lineWidth / 2,
                  width: length,
                  height: lineWidth,
                  borderRadius: lineWidth / 2,
                  backgroundColor: colors.primary,
                  transform: [{rotate: `${angle}deg`}],
                }}
              />
            );
          })}

        {points.map((point, index) => {
          const isLast = index === points.length - 1;
          return (
            <View
              key={`dot-${index}`}
              style={{
                position: 'absolute',
                left: point.x - dotSize / 2,
                top: point.y - dotSize / 2,
                width: dotSize,
                height: dotSize,
                borderRadius: dotSize / 2,
                backgroundColor: isLast ? colors.primary : colors.card,
                borderWidth: isLast ? 0 : moderateScale(2),
                borderColor: colors.primary,
              }}
            />
          );
        })}

        {lastPoint && width > 0 && (
          <View
            style={{
              position: 'absolute',
              left: Math.min(Math.max(lastPoint.x - moderateScale(16), 0), width - moderateScale(32)),
              top: Math.max(lastPoint.y - moderateScale(26), 0),
              paddingHorizontal: moderateScale(7),
              paddingVertical: moderateScale(2),
              borderRadius: moderateScale(8),
              backgroundColor: colors.primary,
            }}>
            <AppText variant="overline" color={colors.textInverse}>
              {lastPoint.value}
            </AppText>
          </View>
        )}
        </View>
      </View>

      {labels && (
        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            marginTop: moderateScale(8),
            marginLeft: AXIS_WIDTH,
          }}>
          {labels.map(label => (
            <AppText key={label} variant="overline" color={colors.textMuted}>
              {label}
            </AppText>
          ))}
        </View>
      )}
    </View>
  );
};

export default TrendChart;
