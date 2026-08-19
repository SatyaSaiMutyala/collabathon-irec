import React, {forwardRef, useImperativeHandle, useMemo, useRef, useState} from 'react';
import {TouchableOpacity, View} from 'react-native';
import {Gesture, GestureDetector} from 'react-native-gesture-handler';
import ViewShot from 'react-native-view-shot';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import AppText from './AppText';

const LINE_WIDTH = 2.5;
// Drop a native touch-move if it hasn't travelled at least this far from the last
// recorded point — a fast finger fires far more move events than a signature needs
// points, and every extra point is another two absolutely-positioned Views (a dot
// and a segment) the active stroke re-renders on *every subsequent* update, which is
// what made drawing feel like it was lagging behind the finger the longer a stroke
// went on. Kept well under LINE_WIDTH so consecutive segments still overlap enough
// to read as one continuous line rather than a chain of separate short dashes —
// too large a value here is what turned the line into visible little dots.
const MIN_POINT_DISTANCE = 1.2;

// Hard ceiling on a single capture() call.
//
// Unlike a thrown error, a capture that never settles isn't something a try/catch can
// do anything about — `await` just suspends forever. react-native-view-shot capturing
// a view built from many small absolutely-positioned, rotated subviews (exactly how a
// multi-stroke signature renders here) can do this on some Android builds. Racing
// against a timeout is the same "compression/capture is an optimisation, never a gate"
// tradeoff compressFileInputs() takes on the web admin side — a slow or stuck capture
// costs the signature image, not the entire submit.
const CAPTURE_TIMEOUT = 4000;

/**
 * Drawn with react-native-gesture-handler rather than the legacy `PanResponder` API
 * this used to use — CompleteProfileScreen (the only caller) sits this inside a
 * KeyboardAwareScrollView, and PanResponder's capture negotiation runs on the JS
 * thread, which can lose the race to the ScrollView's own native scroll recognizer
 * on Android: the parent starts scrolling before the pad's JS callback ever gets a
 * chance to disable it. RNGH's gesture recognition runs natively and arbitrates with
 * an ancestor ScrollView correctly, which is what actually stops the steal.
 *
 * A side benefit: RNGH's pan event `x`/`y` are already relative to the view the
 * gesture is attached to, so none of the old manual `measureInWindow` offset
 * bookkeeping PanResponder's unreliable Android `locationX/Y` needed is required
 * any more.
 */
const SignaturePad = forwardRef(({onChange, onDrawStart, onDrawEnd, error, height = 130}, ref) => {
  const {colors, radius} = useAppTheme();
  const [strokes, setStrokes] = useState([]);
  // Mirrors `strokes`' last point without waiting for the state update to land, so
  // the distance check in `onUpdate` (which can fire again before React re-renders)
  // always compares against where the line actually last landed, not a stale value.
  const lastPointRef = useRef(null);
  const viewShotRef = useRef(null);

  const panGesture = useMemo(
    () =>
      Gesture.Pan()
        .minDistance(0)
        .shouldCancelWhenOutside(false)
        .onBegin(e => {
          onDrawStart?.();
          const point = {x: e.x, y: e.y};
          lastPointRef.current = point;
          setStrokes(prev => [...prev, [point]]);
        })
        .onUpdate(e => {
          const last = lastPointRef.current;
          const dx = e.x - last.x;
          const dy = e.y - last.y;
          if (dx * dx + dy * dy < MIN_POINT_DISTANCE * MIN_POINT_DISTANCE) {
            return;
          }

          const point = {x: e.x, y: e.y};
          lastPointRef.current = point;
          setStrokes(prev => {
            const next = prev.slice();
            next[next.length - 1] = [...next[next.length - 1], point];
            return next;
          });
        })
        .onEnd((_e, success) => {
          if (success) {
            onChange?.(true);
          }
        })
        .onFinalize(() => {
          onDrawEnd?.();
        }),
    [onChange, onDrawStart, onDrawEnd],
  );

  const handleClear = () => {
    setStrokes([]);
    onChange?.(false);
  };

  const isEmpty = strokes.length === 0;

  useImperativeHandle(
    ref,
    () => ({
      /**
       * Snapshots exactly what's currently drawn, pixel for pixel, as a PNG file URI —
       * the same View-based strokes the pad renders, captured rather than reconstructed
       * from the point data, so what gets uploaded is guaranteed to match what the
       * person actually saw themselves sign. Returns null on an empty pad: there is
       * nothing to capture, and a blank image would silently overwrite a signature
       * already on file for a resumed draft that was never redrawn this session.
       *
       * Best-effort, not a gate: react-native-view-shot's native module has to be
       * linked (pod install / a native rebuild, not just a JS reload) to actually
       * work — on a build where that hasn't happened yet, `captureRef` throws
       * synchronously rather than failing quietly. Without this try/catch, that
       * throw propagated straight out of CompleteProfileScreen's persistStep with
       * nothing to catch it, silently breaking the *entire* submit — a step-3
       * submit always has a signature by the time validation lets it through, so
       * every real submit attempt hit this. Catching it here means a missing
       * native module costs the signature image, not the whole submission — the
       * same "attach the file, don't let capturing it block anything else"
       * tradeoff this screen already makes for the profile photo/KYC docs.
       */
      async capture() {
        if (isEmpty || !viewShotRef.current) {
          return null;
        }
        try {
          const result = await Promise.race([
            viewShotRef.current.capture(),
            new Promise((_, reject) => setTimeout(() => reject(new Error('capture timed out')), CAPTURE_TIMEOUT)),
          ]);
          return result ?? null;
        } catch {
          return null;
        }
      },
    }),
    [isEmpty],
  );

  return (
    <View>
      {/* jpg, not png: the pad is always drawn on an opaque background (no
          transparency to preserve), and jpg is what filePart() in
          CompleteProfileScreen already assumes for every other captured image. */}
      <ViewShot ref={viewShotRef} options={{format: 'jpg', quality: 0.9}}>
        <GestureDetector gesture={panGesture}>
          <View
            style={{
              height: moderateScale(height),
              borderWidth: 1.5,
              borderStyle: 'dashed',
              borderColor: error ? colors.danger : colors.border,
              borderRadius: radius.md,
              backgroundColor: colors.background,
              overflow: 'hidden',
            }}>
            {isEmpty && (
              <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
                <AppText variant="caption" color={colors.textMuted}>
                  Sign here
                </AppText>
              </View>
            )}
            {strokes.map((stroke, strokeIndex) => (
              <Stroke key={strokeIndex} points={stroke} color={colors.textPrimary} />
            ))}
          </View>
        </GestureDetector>
      </ViewShot>
      <View style={{flexDirection: 'row', justifyContent: 'space-between', marginTop: moderateScale(4)}}>
        <AppText variant="caption" color={error ? colors.danger : colors.textMuted}>
          {error ?? ' '}
        </AppText>
        {!isEmpty && (
          <TouchableOpacity onPress={handleClear} hitSlop={8}>
            <AppText variant="captionMedium" color={colors.primary}>
              Clear
            </AppText>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
});

/**
 * One pen-down-to-pen-up line, as a dot at every point plus a rotated-rectangle
 * segment connecting each to the next. The dot matters more than it looks: two
 * rotated rectangles only ever touch at a single corner where the line changes
 * direction, so a sharp turn between two segments leaves a small wedge-shaped gap at
 * their joint — invisible when points are dense and turns are gentle, but a sampled
 * signature has sparser points and sharper turns between them, and without the dot
 * to plug each joint the line reads as a chain of separate little dashes instead of
 * one continuous stroke.
 *
 * `React.memo`'d so a signature with multiple strokes (most people lift the pen at
 * least once) doesn't keep re-rendering *finished* strokes on every point the
 * *current* one adds — `points` only changes reference for the stroke actually being
 * drawn, so every other stroke's memo trivially bails out.
 */
const Stroke = React.memo(({points, color}) => (
  <>
    {points.map((p, i) => (
      <View
        key={`dot-${i}`}
        style={{
          position: 'absolute',
          left: p.x - LINE_WIDTH / 2,
          top: p.y - LINE_WIDTH / 2,
          width: LINE_WIDTH,
          height: LINE_WIDTH,
          borderRadius: LINE_WIDTH / 2,
          backgroundColor: color,
        }}
      />
    ))}
    {points.slice(1).map((p, i) => {
      const prev = points[i];
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
            backgroundColor: color,
            transform: [{rotate: `${angle}deg`}],
          }}
        />
      );
    })}
  </>
));

export default SignaturePad;
