function clamp(value) {
  return Math.min(255, Math.max(0, value));
}

function hexToRgb(hex) {
  const normalized = hex.replace('#', '');
  const bigint = parseInt(normalized, 16);
  return [(bigint >> 16) & 255, (bigint >> 8) & 255, bigint & 255];
}

function rgbToHex(r, g, b) {
  const toHex = c => clamp(Math.round(c)).toString(16).padStart(2, '0');
  return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

/** Positive amount lightens toward white, negative darkens toward black. -1..1 */
export function shade(hex, amount) {
  const [r, g, b] = hexToRgb(hex);
  const target = amount > 0 ? 255 : 0;
  const weight = Math.abs(amount);
  return rgbToHex(
    r + (target - r) * weight,
    g + (target - g) * weight,
    b + (target - b) * weight,
  );
}

export function withAlpha(hex, alpha) {
  const [r, g, b] = hexToRgb(hex);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/** Relative luminance, 0 (black) to 1 (white). Used to pick a readable ramp direction. */
export function luminance(hex) {
  const [r, g, b] = hexToRgb(hex).map(channel => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function buildPalette(primary) {
  // `primaryDark` is the pressed/hover weight. Darkening is the normal move, but a
  // near-black primary has no room below it — shade() would return the same colour and
  // the state change would be invisible. Below this threshold the ramp inverts and the
  // "dark" step goes lighter instead.
  const isNearBlack = luminance(primary) < 0.12;

  return {
    primary,
    primaryDark: isNearBlack ? shade(primary, 0.17) : shade(primary, -0.28),
    primaryLight: shade(primary, 0.28),
    primarySoft: shade(primary, 0.86),
    background: '#FFFFFF',
    // Neutral greys, not the warm cream these were: that tint was mixed to sit under a
    // gold accent, and against a black brand it reads as an off-white mistake.
    surface: '#F5F5F5',
    card: '#FFFFFF',
    border: '#DEDEDE',
    textPrimary: '#111111',
    textSecondary: '#5A5A5A',
    textMuted: '#8A8A8A',
    textInverse: '#FFFFFF',
    success: '#1F9254',
    successSoft: '#E6F4EB',
    danger: '#D0342C',
    dangerSoft: '#FBEAE9',
    warning: '#C9922B',
    warningSoft: '#FBF1DD',
    white: '#FFFFFF',
    black: '#000000',
    overlayStrong: 'rgba(10, 12, 20, 0.72)',
    overlaySoft: 'rgba(10, 12, 20, 0.32)',
  };
}

/** Default seed theme — monochrome: black accent on white. Admin can swap `primary`. */
export const DEFAULT_PRIMARY = '#000000';
export const defaultPalette = buildPalette(DEFAULT_PRIMARY);
