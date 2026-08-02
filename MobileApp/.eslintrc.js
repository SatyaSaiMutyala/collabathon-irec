module.exports = {
  root: true,
  extends: '@react-native',
  rules: {
    /*
     * Off, not worked around.
     *
     * Every style in this app that reads `useAppTheme()` — spacing, colours, the radius
     * tokens — is a runtime value. `StyleSheet.create` runs at module scope, before any
     * hook can supply those, so honouring this rule would mean turning ~215 call sites
     * into style factories threaded with the theme: a large mechanical change across
     * every screen, with real regression risk, buying nothing. The perf argument behind
     * the rule is about re-creating style objects on the old bridge, which theme-derived
     * values defeat regardless of where they are declared.
     *
     * Static styles that do NOT need the theme still belong in a StyleSheet, and the
     * components here follow that. The rule is off because it cannot tell the two cases
     * apart — not because inline styles are the house style.
     */
    'react-native/no-inline-styles': 'off',

    /*
     * Off for the same reason. Bitwise operators are the right tool in the one place
     * they appear — unpacking a hex colour in src/theme/palette.js, `(int >> 16) & 255`.
     * The rule exists to catch `&` typed where `&&` was meant, which is not a mistake
     * that survives a colour function returning visibly wrong values.
     */
    'no-bitwise': 'off',
  },
};
