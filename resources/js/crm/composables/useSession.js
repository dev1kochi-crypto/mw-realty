import { ref } from 'vue';
import http from '../api/http';

const user = ref(null);
const navigation = ref({ items: [], account: [] });
const loaded = ref(false);

/** Signed-in account + its menu, loaded once at start-up (GET /auth/me + /navigation). */
function load() {
    return Promise.all([http.get('/auth/me'), http.get('/navigation')]).then(([me, nav]) => {
        user.value = me.data.data;
        navigation.value = nav.data;
        loaded.value = true;
    });
}

/** Badges change as the user works (e.g. join requests answered) — refetch just the menu. */
function refreshNavigation() {
    return http.get('/navigation').then((res) => {
        navigation.value = res.data;
    });
}

function logout() {
    return http.post('/auth/logout').finally(() => {
        window.location.href = window.CrmConfig.loginUrl;
    });
}

export function useSession() {
    return { user, navigation, loaded, load, refreshNavigation, logout };
}
