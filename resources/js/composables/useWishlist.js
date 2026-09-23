import { reactive, readonly, ref } from 'vue';

const wishlistIds = reactive(new Set());
const authenticated = ref(false);
// { name, avatar_url } from the same /customer/session fetch below — this module is the app's
// one shared "am I logged in, and as who" signal, not just wishlist state (see SiteHeader.vue's
// account dropdown, its other consumer).
const user = ref(null);
let initialized = false;

function loadSession() {
    return window.axios.get('/customer/session').then((res) => {
        authenticated.value = res.data.authenticated;
        user.value = res.data.user || null;
        wishlistIds.clear();
        (res.data.wishlist_ids || []).forEach((id) => wishlistIds.add(id));
    }).catch(() => {
        authenticated.value = false;
        user.value = null;
        wishlistIds.clear();
    });
}

/**
 * Patches the shared user object in place — e.g. Profile.vue's settings save changing the name
 * or avatar — so SiteHeader.vue's account dropdown reflects it immediately, without a second
 * /customer/session round-trip or waiting for a full page reload.
 */
export function patchSessionUser(patch) {
    if (user.value) {
        user.value = { ...user.value, ...patch };
    }
}

/**
 * Shared, app-wide session state — one fetch on first use, reused by every card grid
 * (PropertiesDubai, Home, AgentDetails, AgencyDetails) so the same property's heart shows the
 * same filled/unfilled state everywhere without each page re-fetching it, and by SiteHeader.vue
 * to know whether to show "Login" or the account dropdown.
 */
export function useWishlist() {
    if (!initialized) {
        initialized = true;
        loadSession();
    }

    function isWishlisted(propertyId) {
        return wishlistIds.has(propertyId);
    }

    function toggleWishlist(propertyId) {
        if (!authenticated.value) {
            window.location.href = '/login';
            return Promise.resolve();
        }

        return window.axios.post(`/customer/wishlist/${propertyId}`).then((res) => {
            if (res.data.wishlisted) {
                wishlistIds.add(propertyId);
            } else {
                wishlistIds.delete(propertyId);
            }
        });
    }

    return { authenticated: readonly(authenticated), user: readonly(user), isWishlisted, toggleWishlist };
}
