/**
 * A compact Indian-notation price for list/card contexts — "INR 1.50Cr onwards"
 * instead of the full "INR 1,50,00,000 onwards" a detail screen shows. Only INR gets
 * the lakh/crore treatment; every other currency keeps plain grouped digits; crore and
 * lakh are Indian units, and applying them to an AED or USD figure would misstate it,
 * not just reformat it.
 *
 * Detail/accessibility contexts should keep showing the exact number — this is for
 * space-constrained cards only (see the redesign handoff's own worked example: INR
 * 75,000,000 = ₹7,50,00,000 = ₹7.50 Cr, compact card vs. full detail text).
 */
export function formatCompactPrice(value, currency = 'INR') {
  if (!value) {
    return null;
  }

  if (currency !== 'INR') {
    return `${currency} ${new Intl.NumberFormat('en-IN').format(value)} onwards`;
  }

  if (value >= 1e7) {
    return `INR ${(value / 1e7).toFixed(2)}Cr onwards`;
  }
  if (value >= 1e5) {
    return `INR ${(value / 1e5).toFixed(2)}L onwards`;
  }
  return `INR ${new Intl.NumberFormat('en-IN').format(value)} onwards`;
}
