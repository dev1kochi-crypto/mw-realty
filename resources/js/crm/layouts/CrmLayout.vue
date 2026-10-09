<template>
    <div class="portal-shell">
        <aside class="portal-sidebar">
            <div class="portal-brand portal-brand--sidebar">
                <img :src="logoUrl" alt="MW Realty" class="portal-brand__icon portal-brand__icon--only">
            </div>

            <nav class="nav flex-column">
                <template v-for="item in navigation.items" :key="item.key">
                    <div v-if="item.section && item.section !== sectionBefore(item)" class="nav-section-label">{{ item.section }}</div>

                    <div v-if="item.children.length" class="nav-item portal-nav-group">
                        <a href="#" class="nav-link portal-nav-group-toggle" :class="{ active: isActive(item) }"
                           :aria-expanded="isOpen(item)" @click.prevent="toggle(item)">
                            <i class="fas" :class="item.icon"></i> <span>{{ item.label }}</span>
                            <span v-if="item.locked" class="portal-nav-lock" :title="item.lock_reason"><i class="fas fa-lock"></i></span>
                            <i class="fas fa-chevron-down ms-auto portal-nav-chevron"></i>
                        </a>
                        <div class="collapse portal-nav-submenu" :class="{ show: isOpen(item) }">
                            <nav class="nav flex-column">
                                <NavLink v-for="child in item.children" :key="child.key" :item="child" class="py-2" />
                            </nav>
                        </div>
                    </div>
                    <NavLink v-else :item="item" />
                </template>
            </nav>
        </aside>

        <div class="portal-main">
            <header class="portal-topbar" :class="{ 'is-scrolled': scrolled }">
                <div>
                    <div class="fw-bold">{{ user.name }}</div>
                    <span class="portal-account-type">
                        <i class="fas fa-circle" style="font-size:6px;"></i>
                        {{ user.is_super_admin ? 'Super Admin · Global View' : `${ucfirst(user.type)} Account` }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a v-if="user.is_super_admin" :href="backToAdminUrl" class="portal-admin-pill">
                        <i class="fas fa-shield-alt"></i> Back to Admin
                    </a>
                    <template v-if="user.can_upgrade">
                        <span v-if="!user.approved" class="btn-portal-upgrade-cta is-locked" title="Unlocks after KYC approval" aria-disabled="true">
                            <i class="fas fa-lock"></i> Upgrade Plan
                        </span>
                        <RouterLink v-else :to="{ name: 'plans.index' }" class="btn-portal-upgrade-cta">
                            <span class="btn-portal-upgrade-shine"></span>
                            <i class="fas fa-rocket"></i> Upgrade Plan
                        </RouterLink>
                    </template>
                    <a href="/" target="_blank" class="portal-btn-ghost btn btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i> View Site
                    </a>

                    <HelpButton v-if="route.meta.help" :key="route.meta.help" :topic="route.meta.help" />

                    <NotificationsPanel />

                    <div ref="accountMenu" class="dropdown">
                        <button type="button" class="portal-profile-btn" :aria-expanded="accountOpen" @click="accountOpen = !accountOpen">
                            <span class="portal-avatar-circle">{{ initials(user.name) }}</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end portal-profile-menu" :class="{ show: accountOpen }" style="right: 0; left: auto;">
                            <li class="portal-profile-menu-header">
                                <div class="fw-bold">{{ user.name }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ user.is_super_admin ? 'Super Admin' : `${ucfirst(user.type)} Account` }}</div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <template v-if="!user.is_super_admin">
                                <li v-for="item in navigation.account" :key="item.key">
                                    <component :is="item.route ? RouterLink : 'a'" class="dropdown-item" v-bind="item.route ? { to: { name: item.route } } : { href: item.legacy_url }">
                                        <i class="fas me-2" :class="item.icon"></i>{{ item.label }}
                                        <i v-if="item.locked" class="fas fa-lock ms-1 text-warning small" :title="item.lock_reason"></i>
                                    </component>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            </template>
                            <li>
                                <button type="button" class="dropdown-item text-danger" @click="signOut">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="portal-content">
                <!-- KYC not approved yet: rejected (fix & resubmit) / submitted (waiting) / not finished. -->
                <!-- Not on My Profile itself — it shows the full KYC progress card instead. -->
                <div v-if="user.kyc && route.name !== 'profile'" class="portal-status-banner" :class="{ 'is-rejected': user.kyc.state === 'rejected', 'is-waiting': user.kyc.state === 'waiting' }" role="status">
                    <span class="portal-status-banner__icon"><i class="fas" :class="kycBanner.icon"></i></span>
                    <div class="portal-status-banner__body">
                        <div class="portal-status-banner__title">{{ kycBanner.title }}</div>
                        <div class="portal-status-banner__text">{{ kycBanner.text }}</div>
                    </div>
                    <RouterLink v-if="user.kyc.state !== 'waiting'" :to="{ name: 'profile' }" class="btn portal-status-banner__cta">
                        <i class="fas fa-user-edit me-1"></i>{{ user.kyc.state === 'rejected' ? 'Fix & Resubmit' : 'Complete Profile' }}
                    </RouterLink>
                </div>
                <slot />
            </main>
        </div>
    </div>
    <ToastHost />
    <ConfirmDialog />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import NavLink from '../components/NavLink.vue';
import HelpButton from '../components/HelpButton.vue';
import NotificationsPanel from '../components/NotificationsPanel.vue';
import ToastHost from '../components/ToastHost.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useSession } from '../composables/useSession';
import { initials, ucfirst } from '../utils/format';

const { user, navigation, logout } = useSession();
const route = useRoute();
const logoUrl = window.CrmConfig.logoUrl;
const accountOpen = ref(false);
const accountMenu = ref(null);
const openGroups = ref({});
const scrolled = ref(false);

const accountLink = (key) => navigation.value.account.find((item) => item.key === key)?.legacy_url;
const backToAdminUrl = computed(() => accountLink('back-to-admin'));

const kycBanner = computed(() => ({
    rejected: {
        icon: 'fa-ban',
        title: 'Your application needs changes',
        text: `${user.value.kyc?.rejection_reason ? `Reason: ${user.value.kyc.rejection_reason}. ` : ''}Update your profile and resubmit. CRM access unlocks once you're approved.`,
    },
    waiting: {
        icon: 'fa-hourglass-half',
        title: 'KYC submitted — waiting for review',
        text: 'Our team is reviewing your documents. Leads, Reports and listings unlock as soon as your account is approved.',
    },
    incomplete: {
        icon: 'fa-user-clock',
        title: 'Finish your KYC to unlock the CRM',
        text: 'Leads, Reports and property listing unlock once your KYC documents are reviewed and your account is approved.',
    },
})[user.value.kyc?.state] ?? {});

watch(() => route.fullPath, () => {
    accountOpen.value = false;
});

/** A section label is shown above the first item of each section. */
function sectionBefore(item) {
    const items = navigation.value.items;
    return items[items.indexOf(item) - 1]?.section ?? null;
}

function isActive(item) {
    return [item, ...item.children].some((entry) => entry.route && route.meta.nav === entry.key);
}

function isOpen(item) {
    return openGroups.value[item.key] ?? isActive(item);
}

function toggle(item) {
    openGroups.value[item.key] = !isOpen(item);
}

/** A Super Admin signs out of the CMS itself — from the admin panel. */
function signOut() {
    if (user.value.is_super_admin) {
        window.location.href = backToAdminUrl.value;
        return;
    }
    logout();
}

function onDocumentClick(event) {
    if (accountOpen.value && accountMenu.value && !accountMenu.value.contains(event.target)) accountOpen.value = false;
}

function onScroll() {
    scrolled.value = window.scrollY > 4;
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('scroll', onScroll, { passive: true });
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('scroll', onScroll);
});
</script>
