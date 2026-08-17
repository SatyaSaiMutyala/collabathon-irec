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

/**
 * Time-aware greeting, per the client's language pass.
 *
 * Warmth without casualness: "Good morning" reads human where a bare "Hi" reads like a
 * placeholder, and it stays restrained. Boundaries follow ordinary usage — evening from
 * 17:00 — and it deliberately has no late-night variant, since "Good night" is a farewell.
 */
export function greeting(date = new Date()) {
  const hour = date.getHours();

  if (hour < 12) {
    return 'Good morning';
  }

  return hour < 17 ? 'Good afternoon' : 'Good evening';
}

/**
 * Splits a stored "<suffix> <name>" back into its parts — the inverse of how
 * registration joins them (see the module docblock). Unlike `nameParts()`, which
 * throws the honorific away, this keeps it for screens that want to show it as its
 * own field (e.g. ProfileScreen's "Suffix" row, which otherwise has nothing to
 * read — there is no separate `suffix` column, only the combined `name`).
 */
export function splitSuffix(name) {
  const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);

  if (parts.length > 1 && isHonorific(parts[0])) {
    return {suffix: parts[0], rest: parts.slice(1).join(' ')};
  }

  return {suffix: '', rest: parts.join(' ')};
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
