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

export function buildPalette(primary) {
  return {
    primary,
    primaryDark: shade(primary, -0.28),
    primaryLight: shade(primary, 0.28),
    primarySoft: shade(primary, 0.86),
    background: '#FFFFFF',
    surface: '#FAF8F4',
    card: '#FFFFFF',
    border: '#EAE4D6',
    textPrimary: '#12141C',
    textSecondary: '#565D6D',
    textMuted: '#8A90A0',
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

/** Default seed theme — premium gold-on-navy real-estate look. Admin can swap `primary`. */
export const DEFAULT_PRIMARY = '#C9A227';
export const defaultPalette = buildPalette(DEFAULT_PRIMARY);
