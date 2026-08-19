/**
 * Hands a result callback from HomeScreen to MapPickerScreen without putting a
 * function in navigation params — React Navigation warns on that ("Non-serializable
 * values were found in the navigation state") because a function can't survive state
 * persistence/restoration. This app doesn't persist navigation state, so the warning
 * was cosmetic rather than a real bug, but the fix is just as simple as living with
 * the noise: keep the callback out of params entirely, in a module-scoped slot set
 * right before navigating and consumed once by the screen that needs it.
 */
let pendingCallback = null;

export function setMapPickerCallback(callback) {
  pendingCallback = callback;
}

/** Reads and clears the slot in one step, so a second navigate can't reuse a stale callback. */
export function consumeMapPickerCallback() {
  const callback = pendingCallback;
  pendingCallback = null;
  return callback;
}
