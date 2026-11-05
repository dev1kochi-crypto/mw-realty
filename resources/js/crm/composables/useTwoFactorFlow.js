import { reactive } from 'vue';

/**
 * Hand-over from the Security page to the two-factor set-up page (the portal kept these in the
 * session): a set-up already started with the password (moving 2FA to a new phone), or recovery
 * codes just issued.
 */
const flow = reactive({
    pending: null, // { secret, uri, qr_svg } from POST /account/two-factor/setup
    reconfiguring: false,
    recoveryCodes: null,
});

function reset() {
    Object.assign(flow, { pending: null, reconfiguring: false, recoveryCodes: null });
}

export function useTwoFactorFlow() {
    return { flow, reset };
}
