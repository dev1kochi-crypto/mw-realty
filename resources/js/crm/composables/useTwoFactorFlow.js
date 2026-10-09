import { reactive } from 'vue';

/**
 * Hand-over from the Security page to the two-factor set-up page (the portal kept these in the
 * session): a set-up already started with the password (moving 2FA to a new phone), recovery codes
 * just issued, or "make 2FA mandatory for my company" waiting for the account's own set-up.
 */
const flow = reactive({
    pending: null, // { secret, uri, qr_svg } from POST /account/two-factor/setup
    reconfiguring: false,
    recoveryCodes: null,
    enforcing: false,
});

function reset() {
    Object.assign(flow, { pending: null, reconfiguring: false, recoveryCodes: null, enforcing: false });
}

export function useTwoFactorFlow() {
    return { flow, reset };
}
