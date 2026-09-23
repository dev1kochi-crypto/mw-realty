/**
 * Coordinates the login<->signup orb transition (see useAuthPageTransition.js) across the
 * navigation boundary: the leaving page sets `active = true` right before it pushes the new
 * route; the entering page reads it once on mount to know whether to play the matching
 * "orb contracts back down" animation, then clears it. Not reactive on purpose — read once per
 * mount, not watched.
 */
export const authTransitionState = { active: false };
