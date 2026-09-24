<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useWishlist, patchSessionUser } from '../composables/useWishlist';
import { useStaticText } from '../composables/useStaticText';

const { t } = useStaticText();

const navTabs = computed(() => [
    { filter: 'overview', icon: 'icon-home.svg', label: t('profile.nav.overview') },
    { filter: 'wishlist', icon: 'heart.svg', label: t('profile.nav.wishlist') },
    { filter: 'saved-searches', icon: 'search.svg', label: t('profile.nav.saved_searches') },
    { filter: 'enquiries', icon: 'email.svg', label: t('profile.nav.enquiries') },
    { filter: 'settings', icon: 'user.svg', label: t('profile.nav.settings') },
]);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const { toggleWishlist } = useWishlist();

function whatsappUrl(number) {
    return `https://wa.me/${(number || '').replace(/[^0-9]/g, '')}`;
}

const dashboard = ref(null);

function load() {
    window.axios.get('/customer/me').then((res) => {
        dashboard.value = res.data;
        settingsForm.name = res.data.profile.name || '';
        settingsForm.email = res.data.profile.email || '';
        settingsForm.phone = res.data.profile.phone || '';
        settingsForm.location = res.data.profile.location || '';
    }).catch((error) => {
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
    });
}

onMounted(load);

function removeFromWishlist(propertyId) {
    toggleWishlist(propertyId).then(() => {
        if (dashboard.value) {
            dashboard.value.wishlist = dashboard.value.wishlist.filter((p) => p.id !== propertyId);
            dashboard.value.stats.wishlist = dashboard.value.wishlist.length;
        }
    });
}

function deleteSavedSearch(id) {
    window.axios.delete(`/customer/saved-searches/${id}`).then(() => {
        if (dashboard.value) {
            dashboard.value.saved_searches = dashboard.value.saved_searches.filter((s) => s.id !== id);
            dashboard.value.stats.saved_searches = dashboard.value.saved_searches.length;
        }
    });
}

const settingsForm = reactive({ name: '', email: '', phone: '', location: '', password: '', password_confirmation: '' });
const settingsSubmitting = ref(false);
const settingsFeedback = ref(null);

const avatarInput = ref(null);
const avatarFile = ref(null);
const avatarPreview = ref(null);

function handleAvatarChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    avatarFile.value = file;
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
    avatarPreview.value = URL.createObjectURL(file);
}

onBeforeUnmount(() => {
    if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
});

function saveSettings() {
    settingsSubmitting.value = true;
    settingsFeedback.value = null;

    // A file needs multipart/form-data, which axios.put can't send as a plain reactive object —
    // FormData + _method=PUT (Laravel's standard method-spoofing for a POST) covers both the
    // text fields and the optional avatar in one request.
    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('name', settingsForm.name);
    formData.append('email', settingsForm.email);
    formData.append('phone', settingsForm.phone || '');
    formData.append('location', settingsForm.location || '');
    if (settingsForm.password) {
        formData.append('password', settingsForm.password);
        formData.append('password_confirmation', settingsForm.password_confirmation);
    }
    if (avatarFile.value) {
        formData.append('avatar', avatarFile.value);
    }

    window.axios.post('/customer/settings', formData).then((res) => {
        settingsFeedback.value = { type: 'success', text: res.data.message };
        settingsForm.password = '';
        settingsForm.password_confirmation = '';
        if (dashboard.value) {
            dashboard.value.profile.name = settingsForm.name;
            dashboard.value.profile.email = settingsForm.email;
            dashboard.value.profile.phone = settingsForm.phone;
            dashboard.value.profile.location = settingsForm.location;
            if (res.data.avatar_url) {
                dashboard.value.profile.avatar_url = res.data.avatar_url;
            }
        }
        patchSessionUser({ name: settingsForm.name, avatar_url: res.data.avatar_url });
        avatarFile.value = null;
        if (avatarPreview.value) {
            URL.revokeObjectURL(avatarPreview.value);
            avatarPreview.value = null;
        }
    }).catch((error) => {
        const errors = error.response?.data?.errors;
        const text = errors ? Object.values(errors).flat().join(' ') : t('profile.settings.generic_error');
        settingsFeedback.value = { type: 'error', text };
    }).finally(() => {
        settingsSubmitting.value = false;
    });
}

// See Blogs.vue for why this re-run is needed after the async fetch populates the page —
// the legacy tab-switching widget (data-tab-group/data-filter-target) also only scans once.
watch(dashboard, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
</script>

<template>
    <main v-if="dashboard">
        <section class="mw-dashboard">
            <div class="container-ctn">
                <div class="mw-dashboard__layout">

                    <aside class="mw-dashboard__sidebar" data-sticky-sidebar>
                        <div class="mw-dashboard__profile">
                            <span class="mw-dashboard__avatar">
                                <img :src="avatarPreview || dashboard.profile.avatar_url || '/frontend/assets/images/home/testimonial-anna.jpg'" alt="">
                            </span>
                            <p class="mw-dashboard__name">{{ dashboard.profile.name }}</p>
                            <p class="mw-dashboard__email">{{ dashboard.profile.email }}</p>
                        </div>

                        <nav class="mw-dashboard__nav" data-tab-group data-filter-target="#dashboard-panels" :aria-label="t('profile.nav_aria')">
                            <button v-for="(tab, index) in navTabs" :key="tab.filter" type="button" :class="{ 'is-active': index === 0 }" data-tab :data-filter="tab.filter">
                                <img :src="`/frontend/assets/images/icons/${tab.icon}`" alt="">
                                {{ tab.label }}
                            </button>
                        </nav>

                        <form action="/customer/logout" method="post">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <button type="submit" class="mw-dashboard__logout" style="background: transparent; width: 100%; text-align: left; cursor: pointer;">
                                <img src="/frontend/assets/images/icons/arrow-up-right.svg" alt="">
                                {{ t('profile.log_out') }}
                            </button>
                        </form>
                    </aside>

                    <div class="mw-dashboard__content" id="dashboard-panels">

                        <div class="mw-dashboard__panel" data-category="overview">
                            <h1 class="mw-dashboard__panel-title">{{ t('profile.overview.welcome_back') }}, {{ dashboard.profile.name }}</h1>
                            <p class="mw-dashboard__panel-subtitle">{{ t('profile.overview.subtitle') }}</p>

                            <div class="mw-dashboard__stats">
                                <div class="mw-dashboard__stat">
                                    <span class="mw-dashboard__stat-value">{{ dashboard.stats.wishlist }}</span>
                                    <span class="mw-dashboard__stat-label">{{ t('profile.overview.stat_saved_properties') }}</span>
                                </div>
                                <div class="mw-dashboard__stat">
                                    <span class="mw-dashboard__stat-value">{{ dashboard.stats.saved_searches }}</span>
                                    <span class="mw-dashboard__stat-label">{{ t('profile.overview.stat_saved_searches') }}</span>
                                </div>
                                <div class="mw-dashboard__stat">
                                    <span class="mw-dashboard__stat-value">{{ dashboard.stats.enquiries }}</span>
                                    <span class="mw-dashboard__stat-label">{{ t('profile.overview.stat_enquiries_sent') }}</span>
                                </div>
                            </div>

                            <div class="mw-dashboard__activity">
                                <h3>{{ t('profile.overview.recent_activity') }}</h3>
                                <p v-if="!dashboard.activity.length" class="mw-dashboard__panel-subtitle">{{ t('profile.overview.no_activity') }}</p>
                                <div v-for="(item, index) in dashboard.activity" :key="index" class="mw-dashboard__activity-item">
                                    <span class="mw-dashboard__activity-item-icon"><img :src="`/frontend/assets/images/icons/${item.icon}`" alt=""></span>
                                    <span class="mw-dashboard__activity-item-text">
                                        {{ item.text }}
                                        <span class="mw-dashboard__activity-item-time">{{ item.time }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="wishlist" hidden>
                            <h1 class="mw-dashboard__panel-title">{{ t('profile.wishlist.title') }}</h1>
                            <p class="mw-dashboard__panel-subtitle">{{ t('profile.wishlist.subtitle') }}</p>

                            <p v-if="!dashboard.wishlist.length" class="mw-dashboard__panel-subtitle">{{ t('profile.wishlist.empty') }}</p>

                            <div class="mw-dashboard__wishlist-grid">

                                <article v-for="property in dashboard.wishlist" :key="property.slug" class="mw-dubai-card">
                                    <div class="mw-projects__media">
                                        <router-link :to="`/property-details/${property.slug}`"><img :src="property.image" :alt="property.name" class="mw-projects__photo"></router-link>
                                        <span v-if="property.purpose_badge" class="mw-dubai-card__purpose">{{ property.purpose_badge }}</span>
                                        <button type="button" class="mw-dubai-card__fav is-saved" :aria-label="t('profile.wishlist.remove_aria')" @click="removeFromWishlist(property.id)">
                                            <img src="/frontend/assets/images/icons/heart.svg" alt="" width="18" height="18">
                                        </button>
                                        <span class="mw-dubai-card__type">{{ property.type }}</span>
                                    </div>
                                    <div class="mw-dubai-card__body">
                                        <div class="mw-dubai-card__price-row">
                                            <span class="mw-dubai-card__price">{{ property.price }}</span>
                                            <span v-if="property.furnished" class="mw-dubai-card__amenity">{{ t('profile.wishlist.furnished_badge') }}</span>
                                        </div>
                                        <h3 class="mw-dubai-card__title"><router-link :to="`/property-details/${property.slug}`">{{ property.name }}</router-link></h3>
                                        <p class="mw-dubai-card__location">
                                            <img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">
                                            {{ property.location }}
                                        </p>
                                        <div class="mw-dubai-card__stats">
                                            <span v-if="property.beds" class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }}</span>
                                            <span v-if="property.baths" class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }}</span>
                                            <span class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="16" height="16">{{ property.area }}</span>
                                        </div>
                                        <template v-if="property.contact.email || property.contact.phone || property.contact.whatsapp_number">
                                            <div class="mw-dubai-card__divider"></div>
                                            <div class="mw-dubai-card__contacts">
                                                <a v-if="property.contact.email" :href="`mailto:${property.contact.email}`" class="mw-dubai-card__contact-btn">
                                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="16" height="16">
                                                    {{ t('profile.wishlist.email') }}
                                                </a>
                                                <a v-if="property.contact.phone" :href="`tel:${property.contact.phone}`" class="mw-dubai-card__contact-btn">
                                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                                    {{ t('profile.wishlist.call_us') }}
                                                </a>
                                                <a v-if="property.contact.whatsapp_number" :href="whatsappUrl(property.contact.whatsapp_number)" target="_blank" rel="noopener" class="mw-dubai-card__contact-btn">
                                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                                    {{ t('profile.wishlist.whatsapp') }}
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                </article>

                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="saved-searches" hidden>
                            <h1 class="mw-dashboard__panel-title">{{ t('profile.saved_searches.title') }}</h1>
                            <p class="mw-dashboard__panel-subtitle">{{ t('profile.saved_searches.subtitle') }}</p>

                            <p v-if="!dashboard.saved_searches.length" class="mw-dashboard__panel-subtitle">{{ t('profile.saved_searches.empty') }}</p>

                            <div class="mw-dashboard__searches">
                                <div v-for="search in dashboard.saved_searches" :key="search.id" class="mw-dashboard__search-row">
                                    <div class="mw-dashboard__search-row-info">
                                        <p class="mw-dashboard__search-row-title">{{ search.title }}</p>
                                        <p class="mw-dashboard__search-row-meta">{{ search.meta }}</p>
                                    </div>
                                    <div class="mw-dashboard__search-row-actions">
                                        <router-link to="/properties" class="mw-dashboard__link-btn">{{ t('profile.saved_searches.view_results') }}</router-link>
                                        <button type="button" class="mw-dashboard__icon-btn" :aria-label="t('profile.saved_searches.delete_aria')" @click="deleteSavedSearch(search.id)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="enquiries" hidden>
                            <h1 class="mw-dashboard__panel-title">{{ t('profile.enquiries.title') }}</h1>
                            <p class="mw-dashboard__panel-subtitle">{{ t('profile.enquiries.subtitle') }}</p>

                            <p v-if="!dashboard.enquiries.length" class="mw-dashboard__panel-subtitle">{{ t('profile.enquiries.empty') }}</p>

                            <div v-else class="mw-dashboard__table-wrap">
                                <table class="mw-dashboard__table">
                                    <thead>
                                        <tr>
                                            <th>{{ t('profile.enquiries.table_property') }}</th>
                                            <th>{{ t('profile.enquiries.table_sent_to') }}</th>
                                            <th>{{ t('profile.enquiries.table_date') }}</th>
                                            <th>{{ t('profile.enquiries.table_status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="enquiry in dashboard.enquiries" :key="enquiry.id">
                                            <td>{{ enquiry.property }}</td>
                                            <td>{{ enquiry.sent_to }}</td>
                                            <td>{{ enquiry.date }}</td>
                                            <td><span class="mw-dashboard__status">{{ enquiry.status }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mw-dashboard__panel" data-category="settings" hidden>
                            <h1 class="mw-dashboard__panel-title">{{ t('profile.settings.title') }}</h1>
                            <p class="mw-dashboard__panel-subtitle">{{ t('profile.settings.subtitle') }}</p>

                            <form class="mw-dashboard__settings-form" @submit.prevent="saveSettings">
                                <div class="mw-dashboard__avatar-upload">
                                    <span class="mw-dashboard__avatar-upload-preview">
                                        <img :src="avatarPreview || dashboard.profile.avatar_url || '/frontend/assets/images/home/testimonial-anna.jpg'" alt="">
                                    </span>
                                    <div>
                                        <button type="button" class="mw-dashboard__link-btn" @click="avatarInput?.click()">{{ t('profile.settings.change_photo') }}</button>
                                        <p class="mw-dashboard__avatar-upload-hint">{{ t('profile.settings.photo_hint') }}</p>
                                    </div>
                                    <input ref="avatarInput" type="file" accept="image/*" class="sr-only" @change="handleAvatarChange">
                                </div>

                                <div class="mw-dashboard__settings-grid">
                                    <div class="mw-login-form__field">
                                        <label for="settings-name">{{ t('profile.settings.full_name_label') }}</label>
                                        <input type="text" id="settings-name" v-model="settingsForm.name" autocomplete="name" required>
                                    </div>
                                    <div class="mw-login-form__field">
                                        <label for="settings-email">{{ t('profile.settings.email_label') }}</label>
                                        <input type="email" id="settings-email" v-model="settingsForm.email" autocomplete="email" required>
                                    </div>
                                    <div class="mw-login-form__field">
                                        <label for="settings-phone">{{ t('profile.settings.phone_label') }}</label>
                                        <input type="tel" id="settings-phone" v-model="settingsForm.phone" autocomplete="tel">
                                    </div>
                                    <div class="mw-login-form__field">
                                        <label for="settings-location">{{ t('profile.settings.location_label') }}</label>
                                        <input type="text" id="settings-location" v-model="settingsForm.location" autocomplete="address-level2">
                                    </div>
                                </div>

                                <div class="mw-dashboard__settings-grid">
                                    <div class="mw-login-form__field">
                                        <label for="settings-password">{{ t('profile.settings.new_password_label') }}</label>
                                        <input type="password" id="settings-password" v-model="settingsForm.password" :placeholder="t('profile.settings.new_password_placeholder')" autocomplete="new-password">
                                    </div>
                                    <div class="mw-login-form__field">
                                        <label for="settings-password-confirm">{{ t('profile.settings.confirm_password_label') }}</label>
                                        <input type="password" id="settings-password-confirm" v-model="settingsForm.password_confirmation" :placeholder="t('profile.settings.confirm_password_placeholder')" autocomplete="new-password">
                                    </div>
                                </div>

                                <p v-if="settingsFeedback" class="mw-form-feedback" :class="`mw-form-feedback--${settingsFeedback.type}`">{{ settingsFeedback.text }}</p>
                                <button type="submit" class="mw-login-form__submit" :disabled="settingsSubmitting">{{ settingsSubmitting ? t('profile.settings.submitting') : t('profile.settings.submit') }}</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
