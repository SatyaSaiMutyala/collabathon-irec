/**
 * Reading a person's name out of the single `name` field the API stores.
 *
 * Registration builds that field as "<suffix> <full name as per RERA>", so an account
 * created as Mr. + Satya is stored "Mr. Satya". Anything that took the first word as the
 * first name therefore greeted the user as "Hi, Mr." and drew their initials as "MS" —
 * the honorific, not the person.
 *
 * Stripping happens on read rather than at registration: the stored name is what the
 * broker typed for their RERA paperwork and is shown in full on the admin side, and
 * fixing it here also repairs every account that already exists.
 */

/** Matched case-insensitively and with any trailing dot ignored. */
const HONORIFICS = new Set([
  'mr', 'mrs', 'ms', 'miss', 'dr', 'eng', 'er', 'prof', 'shri', 'smt', 'sri',
]);

const isHonorific = part => HONORIFICS.has(part.toLowerCase().replace(/\.$/, ''));

/**
 * The name split into words with a leading honorific dropped.
 *
 * Only a *leading* one, and never the last remaining word: someone whose whole name is
 * recorded as "Dr" should still be shown as "Dr" rather than as nothing at all.
 */
export function nameParts(name) {
  const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);

  if (parts.length > 1 && isHonorific(parts[0])) {
    return parts.slice(1);
  }

  return parts;
}

/** What to greet someone by. Falls back when the account has no usable name. */
export function firstName(name, fallback = '') {
  return nameParts(name)[0] ?? fallback;
}

/** Up to two letters for an avatar with no photo. */
export function initialsOf(name) {
  const parts = nameParts(name);

  if (parts.length === 0) {
    return '?';
  }

  return parts
    .slice(0, 2)
    .map(part => part[0]?.toUpperCase() ?? '')
    .join('');
}
