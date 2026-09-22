import { reactive, readonly, ref } from 'vue';

const wishlistIds = reactive(new Set());
const authenticated = ref(false);
let initialized = false;

function loadSession() {
    return window.axios.get('/customer/session').then((res) => {
        authenticated.value = res.data.authenticated;
        wishlistIds.clear();
        (res.data.wishlist_ids || []).forEach((id) => wishlistIds.add(id));
    }).catch(() => {
        authenticated.value = false;
        wishlistIds.clear();
    });
}

/**
 * Shared, app-wide wishlist-heart state — one fetch on first use, reused by every card grid
 * (PropertiesDubai, Home, AgentDetails, AgencyDetails) so the same property's heart shows the
 * same filled/unfilled state everywhere without each page re-fetching it.
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

    return { authenticated: readonly(authenticated), isWishlisted, toggleWishlist };
}
