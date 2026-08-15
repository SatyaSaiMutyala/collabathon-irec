import {Dimensions, StyleSheet, useWindowDimensions} from 'react-native';

/**
 * Drop-in replacement for `react-native-size-matters`, clamped for large screens.
 *
 * The library scales every size by `shortDimension / 350`, unbounded. That is right
 * for phones — 350pt is roughly the narrowest one, so the ratio stays near 1 — but on
 * a tablet it runs away: an iPad Air 11" is 820pt across, so `scale()` returns 2.34x
 * and `moderateScale()` 1.67x. Every padding, icon, avatar and font in the app comes
 * out half again as large, which is what pushed the welcome screen past the window
 * and got 1.0 (3) rejected under guideline 4.
 *
 * Scaling UI *up* on a tablet is the wrong instinct anyway. A bigger screen is for
 * showing more, not for showing the same thing enlarged — that is why iOS itself does
 * not magnify controls on iPad. So the ratio is capped: phones behave exactly as
 * before, and past phone width the design simply stops growing. Filling the extra
 * width is a layout job (`maxWidth` + centring on the screens themselves), not a
 * scaling one.
 *
 * Import from here, never from 'react-native-size-matters' directly — an unclamped
 * `moderateScale` anywhere reintroduces the same overflow on that one component.
 */

// The library's own baselines, kept so a clamped call returns the identical number to
// the unclamped one on a phone. Changing these silently reflows every screen.
const GUIDELINE_SHORT = 350;
const GUIDELINE_LONG = 680;

/**
 * The ratio ceiling, chosen to sit just above the widest phone: the largest current
 * handset is ~440pt across (430/440 on the Pro Max sizes), i.e. a ratio of ~1.26. At
 * 1.25 every phone in circulation lands under the cap and renders byte-identically to
 * before this file existed; only tablets — the next size class up, at 2.3x — are
 * actually clamped. Raising this re-inflates tablet layouts; lowering it starts
 * shrinking large phones, which is a visible regression on the most common devices.
 */
const MAX_RATIO = 1.25;

// Read once at module load, like the library does. Safe here specifically because both
// ratios are derived from min/max of the two axes rather than from width and height as
// given: the short dimension of a device is the same number in portrait and landscape,
// so a rotation cannot change what these resolve to and there is nothing stale to
// recompute. Layout that *does* have to react to rotation (drawer widths, column
// counts) must use the useWindowDimensions hook instead — see RightDrawer.
const {width, height} = Dimensions.get('window');
const shortDimension = Math.min(width, height);
const longDimension = Math.max(width, height);

const horizontalRatio = Math.min(shortDimension / GUIDELINE_SHORT, MAX_RATIO);
const verticalRatio = Math.min(longDimension / GUIDELINE_LONG, MAX_RATIO);

// Deliberately NOT rounded, because the library these replace is not. Rounding here
// looks harmless and is not: it would shift every padding, font size and icon in the
// app by up to half a point on phones that are nowhere near the cap, turning a change
// that was supposed to affect tablets only into a sub-pixel reflow of the entire
// product. Returning the same floats means a device under MAX_RATIO gets numbers
// identical to before this file existed.

/** Widths, paddings, radii — anything measured across the screen. */
export const scale = size => size * horizontalRatio;

/** Heights and vertical rhythm. */
export const verticalScale = size => size * verticalRatio;

/**
 * The one to reach for by default: applies only `factor` of the full scaling (half by
 * default), so text and controls grow more gently than the layout around them.
 */
export const moderateScale = (size, factor = 0.5) =>
  size + (scale(size) - size) * factor;

export const moderateVerticalScale = (size, factor = 0.5) =>
  size + (verticalScale(size) - size) * factor;

/**
 * True on tablets and on any window wide enough to lay out as one — a phone in
 * landscape does not qualify, and an iPad in Split View stops qualifying when the
 * window is narrowed, which is the intent. Read it from `useWindowDimensions()` at
 * render time rather than from this module's constants when the answer has to survive
 * a resize.
 */
export const TABLET_BREAKPOINT = 600;

export const isWideScreen = w => w >= TABLET_BREAKPOINT;

/**
 * How wide a single column of content is allowed to get. Past this, a card's text runs
 * to two-word lines with a hand's width of blank either side — the "crowded, laid out
 * ... difficult to use" note in the same rejection. A fixed point value on purpose:
 * this is a bound ON the scaling, so scaling it would defeat it.
 */
export const CONTENT_MAX_WIDTH = 560;

const columnStyles = StyleSheet.create({
  column: {
    width: '100%',
    maxWidth: CONTENT_MAX_WIDTH,
    alignSelf: 'center',
  },
});

/**
 * The centred, width-capped column style on a wide window, and `false` on a phone —
 * i.e. safe to drop straight into a style array, where a falsy entry is ignored:
 *
 *   <View style={[styles.body, useContentColumn()]}>
 *
 * A hook rather than a module constant because the answer has to change when an iPad
 * window is resized or rotated, which a value read once at import cannot do.
 */
export const useContentColumn = () => {
  const {width: windowWidth} = useWindowDimensions();
  return isWideScreen(windowWidth) && columnStyles.column;
};
