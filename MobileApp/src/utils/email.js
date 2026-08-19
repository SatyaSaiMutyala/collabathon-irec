/**
 * Catches the class of typo a bare format regex can't: `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`
 * (the pattern this app has always validated emails with) accepts "gmail.como" just
 * as happily as "gmail.com" — a stray trailing letter is a syntactically valid
 * domain as far as that regex is concerned, so it never gets caught until the OTP
 * silently never arrives. Comparing the domain against the handful of providers
 * almost every broker actually uses is what catches it instead.
 */
const COMMON_EMAIL_DOMAINS = [
  'gmail.com',
  'yahoo.com',
  'outlook.com',
  'hotmail.com',
  'icloud.com',
  'live.com',
  'rediffmail.com',
  'protonmail.com',
  'aol.com',
  'msn.com',
];

/** Classic dynamic-programming edit distance — small strings only, no need for anything fancier. */
function levenshtein(a, b) {
  const rows = a.length + 1;
  const cols = b.length + 1;
  const dp = Array.from({length: rows}, () => new Array(cols).fill(0));

  for (let i = 0; i < rows; i++) dp[i][0] = i;
  for (let j = 0; j < cols; j++) dp[0][j] = j;

  for (let i = 1; i < rows; i++) {
    for (let j = 1; j < cols; j++) {
      dp[i][j] =
        a[i - 1] === b[j - 1]
          ? dp[i - 1][j - 1]
          : 1 + Math.min(dp[i - 1][j - 1], dp[i - 1][j], dp[i][j - 1]);
    }
  }

  return dp[rows - 1][cols - 1];
}

/**
 * `null` when the address already matches a known provider (or isn't close to one
 * at all — a genuinely unusual but real domain should never get "corrected" into a
 * popular one just because it happens to share a few letters). Otherwise the
 * provider it most likely meant to type.
 */
export function suggestEmailDomain(email) {
  const at = email.lastIndexOf('@');
  if (at === -1 || at === email.length - 1) {
    return null;
  }

  const domain = email.slice(at + 1).trim().toLowerCase();
  if (!domain || COMMON_EMAIL_DOMAINS.includes(domain)) {
    return null;
  }

  for (const known of COMMON_EMAIL_DOMAINS) {
    // Length-gated before the real (more expensive) comparison — nothing worth
    // suggesting differs from a known domain by more than a couple of characters.
    if (Math.abs(domain.length - known.length) <= 2 && levenshtein(domain, known) <= 2) {
      return known;
    }
  }

  return null;
}
