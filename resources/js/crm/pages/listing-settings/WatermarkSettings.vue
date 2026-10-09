<template>
    <div v-if="!data" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="wm-page-head">
            <h1>Listing Settings</h1>
            <p>{{ data.is_admin ? 'The default watermark for MW Realty listings and for every agency or agent that hasn\'t set their own.' : 'How your listing photos are branded when you upload them.' }}</p>
        </div>

        <nav class="wm-tabs">
            <RouterLink v-for="(label, key) in tabs" :key="key" :to="{ query: key === 'default' ? {} : { tab: key } }" :class="{ 'is-active': tab === key }">{{ label }}<span v-if="key === 'accounts'" class="wm-tab-count">{{ number(data.own_count) }}</span></RouterLink>
        </nav>

        <!-- ============ Super Admin: every agency / agent watermark ============ -->
        <template v-if="tab === 'accounts'">
            <div class="wm-notice"><i class="fas fa-circle-info"></i><span>An account's own watermark always wins. Accounts with none, or with theirs turned off, get your <RouterLink :to="{ query: {} }">default watermark</RouterLink>. Agents in an agency use the agency's.</span></div>
            <div class="wm-panel">
                <div class="wm-toolbar">
                    <form @submit.prevent="searchAccounts">
                        <input v-model="searchInput" type="search" class="form-control form-control-sm" placeholder="Search agency, agent or email" maxlength="100">
                        <button class="btn btn-sm btn-portal-light" type="submit"><i class="fas fa-magnifying-glass"></i></button>
                    </form>
                    <div class="wm-filter">
                        <RouterLink v-for="(label, key) in { '': 'All', on: 'On', off: 'Off' }" :key="key" :to="{ query: cleanQuery({ tab: 'accounts', q: route.query.q, status: key }) }" :class="{ 'is-active': (accounts?.status ?? '') === key }">{{ label }}</RouterLink>
                    </div>
                </div>
                <div v-if="!accounts" class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                <div v-else class="table-responsive">
                    <table class="table portal-table align-middle mb-0 wm-accounts">
                        <thead><tr><th class="wm-sn">#</th><th>Account</th><th>Watermark</th><th>Position</th><th>Opacity · Size</th><th>Status</th><th>Updated</th></tr></thead>
                        <tbody>
                            <tr v-for="(account, i) in accounts.data" :key="account.id">
                                <td class="wm-sn">{{ accounts.meta.from + i }}</td>
                                <td>
                                    <div class="fw-semibold">{{ account.name }}</div>
                                    <div class="small text-muted">{{ account.kind }} · {{ account.email }}</div>
                                </td>
                                <td>
                                    <div class="wm-thumb">
                                        <span v-if="account.settings.type === 'text'" :style="{ color: account.settings.color }">{{ account.settings.text || '—' }}</span>
                                        <AuthImage v-else-if="account.settings.has_image" :src="`/listing-settings/watermark/image?account=${account.id}&v=${account.settings.image_version}`" alt="" loading="lazy" />
                                        <i v-else class="fas fa-image text-muted"></i>
                                    </div>
                                </td>
                                <td class="small">{{ positionLabels[account.settings.position] ?? '—' }}</td>
                                <td class="small">{{ account.settings.opacity }}% · {{ account.settings.size }}%</td>
                                <td>
                                    <span v-if="account.settings.enabled" class="wm-state is-on m-0"><i class="fas fa-circle" style="font-size: 5px;"></i>On</span>
                                    <span v-else class="wm-state is-off m-0" title="Uses the default watermark">Off · default</span>
                                </td>
                                <td class="small text-muted">{{ account.updated_at ? timeAgo(account.updated_at) : '' }}</td>
                            </tr>
                            <tr v-if="!accounts.data.length"><td colspan="7" class="text-center text-muted py-5">{{ accounts.search !== '' || accounts.status ? 'No accounts match.' : 'No agency or agent has set up their own watermark yet — all use the default.' }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="accounts && accounts.meta.last_page > 1" class="p-3 border-top"><CrmPagination :meta="accounts.meta" @change="goToPage" /></div>
            </div>
        </template>

        <template v-else>
            <div v-if="data.read_only" class="wm-notice"><i class="fas fa-building"></i><span>Your listings belong to <strong>{{ data.brand }}</strong>, so their watermark is used on your photos. Only the agency can change it.</span></div>
            <div v-else-if="data.using_default" class="wm-notice"><i class="fas fa-circle-info"></i><span>You haven't turned on a watermark of your own, so the <strong>MW Realty default watermark</strong> is added to your listing photos. Set up yours below to use it instead.</span></div>

            <form class="wm-layout" @submit.prevent="save">
                <div class="wm-panel">
                    <fieldset class="wm-settings" :disabled="data.read_only">
                        <div class="wm-row">
                            <div class="wm-switch-row">
                                <div>
                                    <div class="fw-bold" style="font-size: .9rem;">{{ data.is_admin ? 'Default watermark' : 'Watermark' }} <span class="wm-state" :class="s.enabled ? 'is-on' : 'is-off'">{{ s.enabled ? 'On' : 'Off' }}</span></div>
                                    <div class="wm-help">{{ data.is_admin ? 'Added to photos uploaded from now on — your listings and accounts without their own.' : 'Added to every listing photo uploaded from now on.' }}</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input v-model="s.enabled" class="form-check-input" type="checkbox" role="switch" aria-label="Add watermark to photos">
                                </div>
                            </div>
                        </div>

                        <div class="wm-row">
                            <div class="wm-row-title">Type</div>
                            <div class="wm-segment">
                                <label><input v-model="s.type" type="radio" value="image"><span><i class="fas fa-image"></i>Logo</span></label>
                                <label><input v-model="s.type" type="radio" value="text"><span><i class="fas fa-font"></i>Text</span></label>
                            </div>
                        </div>

                        <div v-show="s.type === 'image'" class="wm-row">
                            <div class="wm-row-title">Logo</div>
                            <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="onFile">
                            <div class="wm-logo">
                                <div class="wm-logo-box">
                                    <img v-if="imageSrc" :src="imageSrc" alt="Watermark logo">
                                    <i v-else class="fas fa-image fa-lg"></i>
                                </div>
                                <div>
                                    <div v-if="!data.read_only" class="wm-logo-actions">
                                        <button type="button" class="btn btn-portal-light" @click="fileInput.click()"><i class="fas fa-arrow-up-from-bracket me-1"></i><span>{{ imageSrc ? 'Replace' : 'Upload' }}</span></button>
                                        <button v-if="imageSrc" type="button" class="btn btn-link text-danger p-0" @click="removeImage">Remove</button>
                                    </div>
                                    <div class="wm-help">PNG with a transparent background works best. Max 4 MB.</div>
                                </div>
                            </div>
                            <div v-if="errors.image" class="text-danger small mt-2">{{ errors.image[0] }}</div>
                        </div>

                        <div v-show="s.type === 'text'" class="wm-row">
                            <label class="wm-row-title" for="wmText">Text &amp; colour</label>
                            <div class="wm-text-row">
                                <input id="wmText" v-model="s.text" type="text" class="form-control form-control-sm" maxlength="60" :placeholder="`e.g. ${data.brand}`">
                                <div class="wm-swatches">
                                    <button v-for="swatch in swatches" :key="swatch" type="button" class="wm-swatch" :class="{ 'is-active': s.color.toLowerCase() === swatch }" :style="{ background: swatch }" :aria-label="`Colour ${swatch}`" @click="s.color = swatch"></button>
                                    <input v-model="s.color" type="color" class="wm-color" aria-label="Custom colour">
                                </div>
                            </div>
                            <div v-if="errors.text" class="text-danger small mt-1">{{ errors.text[0] }}</div>
                        </div>

                        <!-- Position and opacity / size side by side, so the panel fits on one screen. -->
                        <div class="wm-row wm-place">
                            <div>
                                <div class="wm-row-title">Position</div>
                                <div class="wm-grid" role="radiogroup" aria-label="Position">
                                    <button v-for="(label, key) in positionLabels" :key="key" type="button" role="radio" :title="label" :aria-label="label" :aria-checked="s.position === key ? 'true' : 'false'" :class="{ 'is-active': s.position === key }" @click="s.position = key"></button>
                                </div>
                                <div class="wm-help mt-1">{{ positionLabels[s.position] ?? '' }}</div>
                            </div>
                            <div class="wm-sliders">
                                <div>
                                    <div class="wm-row-title"><label for="wmOpacity" class="m-0">Opacity</label><output>{{ s.opacity }}%</output></div>
                                    <input id="wmOpacity" v-model.number="s.opacity" type="range" class="wm-range" min="5" max="100">
                                </div>
                                <div>
                                    <div class="wm-row-title"><label for="wmSize" class="m-0">Size</label><output>{{ s.size }}%</output></div>
                                    <input id="wmSize" v-model.number="s.size" type="range" class="wm-range" min="5" max="80">
                                </div>
                            </div>
                        </div>

                        <div v-if="!data.read_only" class="wm-footer">
                            <button type="button" class="btn btn-portal-light" @click="reset">Cancel</button>
                            <button type="submit" class="btn btn-portal-primary" :disabled="saving">Save changes</button>
                        </div>
                    </fieldset>
                </div>

                <div class="wm-panel wm-preview-panel">
                    <div class="wm-preview-head"><span>Preview</span><span class="wm-help fw-normal">Sample listing photo</span></div>
                    <div ref="preview" class="wm-preview" :class="{ 'is-off': !s.enabled }">
                        <img class="wm-photo" src="/frontend/assets/images/home/hero-bg.jpg" alt="Sample listing photo" @load="render">
                        <div ref="mark" class="wm-mark">
                            <img v-if="s.type === 'image' && imageSrc" :src="imageSrc" alt="" @load="render">
                            <span v-show="s.type === 'text'" ref="markText" :style="{ color: s.color }">{{ s.text.trim() || data.brand }}</span>
                        </div>
                        <div class="wm-preview-off"><i class="fas fa-circle-pause me-1"></i>Watermark is off — photos are uploaded without it{{ data.is_admin ? '' : (data.using_default ? ' (the MW Realty default is used instead)' : '') }}.</div>
                    </div>
                </div>
            </form>
        </template>
    </div>
</template>

<script setup>
/**
 * Listing Settings › Watermark (resources/views/portal/watermark/edit). Agency / independent agent:
 * their own watermark (an agency's agents see the agency's, read-only). Super Admin: "Default
 * watermark" and "Agency & agent watermarks" (?tab=accounts, 20 per page). The preview uses the
 * same geometry as App\Services\Watermark::apply().
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import AuthImage from '../../components/AuthImage.vue';
import CrmPagination from '../../components/CrmPagination.vue';
import { useToast } from '../../composables/useToast';
import { number, timeAgo } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { success, error: toastError } = useToast();

const positionLabels = { tl: 'Top left', tc: 'Top centre', tr: 'Top right', ml: 'Middle left', mc: 'Centre', mr: 'Middle right', bl: 'Bottom left', bc: 'Bottom centre', br: 'Bottom right' };
const swatches = ['#ffffff', '#000000', '#244373', '#ca2844'];

const data = ref(null);
const loadError = ref('');
const s = reactive({ enabled: false, type: 'image', text: '', color: '#ffffff', opacity: 50, size: 25, position: 'br' });
const savedImageUrl = ref(null); // the stored logo, as an object URL
const imageSrc = ref(null); // what the form shows now (stored logo, a picked file, or nothing)
const imageFile = ref(null);
const removeImageFlag = ref(false);
const errors = ref({});
const saving = ref(false);
const fileInput = ref(null);
const preview = ref(null);
const mark = ref(null);
const markText = ref(null);

const accounts = ref(null);
const searchInput = ref('');

const tabs = computed(() => (data.value?.is_admin ? { default: 'Default watermark', accounts: 'Agency & agent watermarks' } : { default: 'Watermark' }));
const tab = computed(() => (data.value?.is_admin && route.query.tab === 'accounts' ? 'accounts' : 'default'));
const cleanQuery = (query) => Object.fromEntries(Object.entries(query).filter(([, v]) => v !== undefined && v !== null && v !== ''));

function releaseSavedImage() {
    if (savedImageUrl.value) URL.revokeObjectURL(savedImageUrl.value);
    savedImageUrl.value = null;
}

function applySettings(settings) {
    Object.assign(s, {
        enabled: settings.enabled, type: settings.type, text: settings.text, color: settings.color,
        opacity: settings.opacity, size: settings.size, position: settings.position,
    });
    imageFile.value = null;
    removeImageFlag.value = false;
    errors.value = {};
    if (fileInput.value) fileInput.value.value = '';
    releaseSavedImage();
    imageSrc.value = null;
    if (!settings.has_image) return Promise.resolve();
    return http.get('/listing-settings/watermark/image', { params: { v: settings.image_version }, responseType: 'blob' })
        .then((res) => {
            savedImageUrl.value = URL.createObjectURL(res.data);
            imageSrc.value = savedImageUrl.value;
        })
        .catch(() => {});
}

function load() {
    loadError.value = '';
    return http.get('/listing-settings/watermark')
        .then((res) => {
            data.value = res.data;
            return applySettings(res.data.settings);
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function reset() {
    applySettings(data.value.settings);
}

function onFile() {
    const file = fileInput.value.files[0];
    if (!file) return;
    imageFile.value = file;
    removeImageFlag.value = false;
    const reader = new FileReader();
    reader.onload = (e) => { imageSrc.value = e.target.result; };
    reader.readAsDataURL(file);
}

function removeImage() {
    fileInput.value.value = '';
    imageFile.value = null;
    removeImageFlag.value = true;
    imageSrc.value = null;
}

function save() {
    saving.value = true;
    errors.value = {};
    const body = new FormData();
    body.append('enabled', s.enabled ? '1' : '0');
    body.append('type', s.type);
    body.append('text', s.text);
    body.append('color', s.color);
    body.append('opacity', s.opacity);
    body.append('size', s.size);
    body.append('position', s.position);
    body.append('remove_image', removeImageFlag.value ? '1' : '0');
    if (imageFile.value) body.append('image', imageFile.value);
    http.post('/listing-settings/watermark', body)
        .then((res) => {
            success(res.data.message);
            data.value.settings = res.data.settings;
            // An account may have just switched its own on / off — load() refreshes the "default is used" notice too.
            return data.value.is_admin ? applySettings(res.data.settings) : load();
        })
        .catch((e) => {
            if (e.response?.status === 422 && e.response.data.errors) errors.value = e.response.data.errors;
            else toastError(errorMessage(e));
        })
        .finally(() => { saving.value = false; });
}

// Same geometry as App\Services\Watermark::apply(): size = % of photo width, margin = 3% of the short side.
function render() {
    const box = preview.value;
    const el = mark.value;
    if (!box || !el) return;
    const W = box.clientWidth;
    const H = box.clientHeight;
    const margin = Math.round(Math.min(W, H) * 0.03);
    const targetW = (W * s.size) / 100;
    const pos = s.position;

    if (s.type === 'text' && markText.value) {
        markText.value.style.fontSize = '100px';
        markText.value.style.fontSize = `${(100 * targetW) / Math.max(1, markText.value.scrollWidth)}px`;
        el.style.width = 'auto';
    } else {
        el.style.width = `${targetW}px`;
    }
    el.style.opacity = s.opacity / 100;

    const mw = el.offsetWidth;
    const mh = el.offsetHeight;
    el.style.left = `${pos[1] === 'l' ? margin : pos[1] === 'r' ? W - mw - margin : (W - mw) / 2}px`;
    el.style.top = `${pos[0] === 't' ? margin : pos[0] === 'b' ? H - mh - margin : (H - mh) / 2}px`;
}

watch([s, imageSrc, () => data.value?.brand, tab], () => nextTick(render), { deep: true });

/* ---------- Agency & agent watermarks (Super Admin) ---------- */
function loadAccounts() {
    accounts.value = null;
    return http.get('/listing-settings/watermark/accounts', { params: { q: route.query.q, status: route.query.status, page: route.query.page } })
        .then((res) => {
            accounts.value = res.data;
            searchInput.value = res.data.search;
        })
        .catch((e) => toastError(errorMessage(e)));
}

function searchAccounts() {
    router.push({ query: cleanQuery({ tab: 'accounts', status: route.query.status, q: searchInput.value.trim() }) });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

watch(() => [route.query, data.value?.is_admin], () => {
    if (route.name === 'listing-settings' && tab.value === 'accounts') loadAccounts();
}, { immediate: true, deep: true });

onMounted(() => {
    load();
    window.addEventListener('resize', render);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', render);
    releaseSavedImage();
});
</script>
