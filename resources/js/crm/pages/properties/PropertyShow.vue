<template>
    <div v-if="!p" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: section.routes.index }">Back to {{ section.title.toLowerCase() }}</RouterLink></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <nav class="portal-muted small">
                <RouterLink :to="{ name: section.routes.index }" class="text-decoration-none">{{ section.title }}</RouterLink>
                <i class="fas fa-chevron-right mx-1" style="font-size: 0.65rem;"></i>
                <span>{{ p.reference_no }}</span>
            </nav>
            <div class="d-flex gap-2">
                <RouterLink :to="{ name: section.routes.index }" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to List</RouterLink>
                <RouterLink :to="{ name: section.routes.edit, params: { id: p.id } }" class="btn btn-portal-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</RouterLink>
            </div>
        </div>

        <!-- Hero gallery -->
        <div class="pd-hero" :class="{ 'pd-hero--single': photos.length < 3 }">
            <button v-for="(url, i) in photos.slice(0, photos.length < 3 ? 1 : 3)" :key="i" type="button" class="pd-hero__item" @click="openLightbox(i)">
                <img :src="url" :alt="p.title">
                <span v-if="i === 2 && photos.length > 3" class="pd-hero__more">+{{ photos.length - 3 }} photos</span>
            </button>
            <div class="pd-hero__badges">
                <span v-if="p.listing_label" class="pd-pill">{{ p.listing_label }}</span>
                <span class="pd-pill" :class="p.status ? 'pd-pill--active' : 'pd-pill--inactive'">{{ p.status ? 'Active' : 'Inactive' }}</span>
                <span v-if="p.featured" class="pd-pill pd-pill--featured"><i class="fas fa-star me-1"></i>Featured</span>
            </div>
            <span v-if="p.gallery.length" class="pd-pill pd-hero__count"><i class="fas fa-images me-1"></i>{{ photos.length }}</span>
        </div>

        <!-- Title / price / stats -->
        <div class="pd-head mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="flex-grow-1" style="min-width: 0;">
                    <h1 class="pd-head__title">{{ p.title || 'Untitled property' }}</h1>
                    <p class="pd-head__address"><i class="fas fa-map-marker-alt me-1"></i>{{ p.address || '—' }}</p>
                </div>
                <div class="text-md-end">
                    <div class="pd-head__price-label">{{ p.listing_label ? `${p.listing_label} price` : 'Price' }}</div>
                    <div class="pd-head__price">{{ p.price }}</div>
                </div>
            </div>
            <div v-if="p.stats.length" class="pd-stats">
                <div v-for="[icon, value, label] in p.stats" :key="label" class="pd-stat">
                    <span class="pd-stat__icon"><i class="fas" :class="icon"></i></span>
                    <div><div class="pd-stat__value">{{ value }}</div><div class="pd-stat__label">{{ label }}</div></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Overview (per language) -->
                <div class="pd-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="pd-card__title mb-0">Overview</div>
                        <ul v-if="p.languages.length > 1" class="nav portal-lang-tabs" role="tablist">
                            <li v-for="lang in p.languages" :key="lang.code" class="nav-item" role="presentation">
                                <button class="nav-link" :class="{ active: langTab === lang.code }" type="button" role="tab" @click="langTab = lang.code">{{ lang.name }}</button>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div v-for="lang in p.languages" v-show="langTab === lang.code" :key="lang.code" class="tab-pane fade show active" role="tabpanel" :dir="lang.code === 'ar' ? 'rtl' : null">
                            <h5 v-if="p.languages.length > 1 && lang.title" class="fw-bold mb-3">{{ lang.title }}</h5>
                            <ul v-if="lang.highlights.length" class="pd-highlights">
                                <li v-for="(line, i) in lang.highlights" :key="i"><i class="fas fa-check-circle"></i><span>{{ line }}</span></li>
                            </ul>
                            <template v-if="lang.description !== ''">
                                <div :ref="(el) => richEls[lang.code] = el" class="pd-rich" :class="{ 'is-collapsed': collapsed[lang.code] !== false }" v-html="lang.description"></div>
                                <button v-if="clipped[lang.code]" type="button" class="btn btn-link p-0 fw-bold text-decoration-none" @click="collapsed[lang.code] = collapsed[lang.code] === false">
                                    <template v-if="collapsed[lang.code] !== false">Read more <i class="fas fa-chevron-down ms-1"></i></template>
                                    <template v-else>Show less <i class="fas fa-chevron-up ms-1"></i></template>
                                </button>
                            </template>
                            <p v-else class="portal-muted mb-0">No description added for this language.</p>

                            <div class="row g-3 mt-3 pt-3 border-top">
                                <div v-for="[key, label] in [['address', 'Address'], ['community', 'Community'], ['city', 'City'], ['country', 'Country']]" :key="key" class="col-6 col-md-3">
                                    <div class="pd-fact__label">{{ label }}</div><div class="pd-fact__value">{{ lang[key] ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property details -->
                <div v-if="p.facts.length" class="pd-card">
                    <div class="pd-card__title">Property Details</div>
                    <div class="pd-facts">
                        <div v-for="[icon, label, value] in p.facts" :key="label" class="pd-fact">
                            <i class="fas" :class="icon"></i>
                            <div style="min-width: 0;"><div class="pd-fact__label">{{ label }}</div><div class="pd-fact__value">{{ value }}</div></div>
                        </div>
                    </div>
                </div>

                <!-- Amenities / Easy Access / Attributes -->
                <div v-for="group in p.chip_groups" :key="group.label" class="pd-card">
                    <div class="pd-card__title">{{ group.label }}</div>
                    <div class="pd-chips">
                        <span v-for="(row, i) in group.rows" :key="i" class="pd-chip">
                            <img v-if="row.icon" :src="row.icon" alt=""><i v-else class="fas" :class="group.icon"></i>
                            {{ row.label }}
                        </span>
                    </div>
                </div>

                <!-- Floor Plans -->
                <div v-if="p.floor_plans.length || p.floor_plan_file" class="pd-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="pd-card__title mb-0">Floor Plans</div>
                        <a v-if="p.floor_plan_file" :href="p.floor_plan_file" target="_blank" class="btn btn-portal-light btn-sm"><i class="fas fa-file-download me-1"></i>Download</a>
                    </div>
                    <div class="row g-3">
                        <div v-for="(plan, i) in p.floor_plans" :key="i" class="col-md-6">
                            <div class="pd-plan">
                                <a v-if="plan.image" :href="plan.image" target="_blank"><img :src="plan.image" :alt="plan.label"></a>
                                <div class="pd-plan__body">
                                    <div class="fw-bold">{{ plan.label }}</div>
                                    <div class="portal-muted small">
                                        <template v-if="plan.size_from || plan.size_to"><i class="fas fa-ruler-combined me-1"></i>{{ plan.size_from }}<template v-if="plan.size_to"> &ndash; {{ plan.size_to }}</template> sq.ft </template>
                                        <span v-if="plan.price_from || plan.price_to" class="ms-2"><i class="fas fa-tag me-1"></i>{{ plan.price_from }}<template v-if="plan.price_to"> &ndash; {{ plan.price_to }}</template> {{ p.currency }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Nearby -->
                <div v-if="hasMap || p.nearby_groups.length" class="pd-card">
                    <div class="pd-card__title">Location &amp; Nearby</div>
                    <template v-if="hasMap">
                        <iframe class="pd-map mb-3" loading="lazy" referrerpolicy="no-referrer-when-downgrade" :src="`https://maps.google.com/maps?q=${p.latitude},${p.longitude}&z=15&output=embed`"></iframe>
                        <div class="portal-muted small mb-3"><i class="fas fa-crosshairs me-1"></i>{{ p.latitude }}, {{ p.longitude }}</div>
                    </template>
                    <div v-for="group in p.nearby_groups" :key="group.type" class="mb-3">
                        <div class="pd-fact__label mb-2">{{ group.type }}</div>
                        <div class="pd-chips">
                            <span v-for="(name, i) in group.places" :key="i" class="pd-chip"><i class="fas fa-map-pin"></i>{{ name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="pd-sticky">
                    <!-- Agent -->
                    <div class="pd-card">
                        <div class="pd-card__title">Listing Agent</div>
                        <template v-if="p.agent">
                            <div class="pd-agent">
                                <img v-if="p.agent.avatar" :src="p.agent.avatar" class="pd-agent__avatar" alt="">
                                <span v-else class="pd-agent__avatar">{{ p.agent.name.charAt(0).toUpperCase() }}</span>
                                <div style="min-width: 0;">
                                    <div class="fw-bold">{{ p.agent.name }}</div>
                                    <div v-if="p.agent.company" class="portal-muted small"><i class="fas fa-building me-1"></i>{{ p.agent.company }}</div>
                                    <div v-if="p.agent.brn_number" class="portal-muted small">BRN: {{ p.agent.brn_number }}</div>
                                </div>
                            </div>
                            <div class="pd-contact">
                                <a v-if="p.agent.phone" :href="`tel:${p.agent.phone}`"><i class="fas fa-phone"></i>{{ p.agent.phone }}</a>
                                <a v-if="p.agent.whatsapp" :href="`https://wa.me/${p.agent.whatsapp.replace(/\D/g, '')}`" target="_blank"><i class="fab fa-whatsapp"></i>{{ p.agent.whatsapp }}</a>
                                <a v-if="p.agent.email" :href="`mailto:${p.agent.email}`"><i class="fas fa-envelope"></i>{{ p.agent.email }}</a>
                            </div>
                        </template>
                        <div v-else class="portal-muted"><i class="fas fa-user-tie me-2"></i>No agent assigned</div>
                        <div v-if="p.is_admin" class="portal-muted small mt-3 pt-3 border-top"><i class="fas fa-user-shield me-1"></i>Listed by: <span class="fw-semibold">{{ p.owner_label }}</span></div>
                    </div>

                    <a v-if="p.virtual_tour_url" :href="p.virtual_tour_url" target="_blank" rel="noopener" class="btn btn-portal-primary w-100 mb-4"><i class="fas fa-vr-cardboard me-2"></i>Take the Virtual Tour</a>

                    <!-- Listing info -->
                    <div class="pd-card">
                        <div class="pd-card__title">Listing Info</div>
                        <dl class="pd-seo mb-0">
                            <dt>Created</dt><dd>{{ p.created_at ?? '—' }}</dd>
                            <dt>Last Updated</dt><dd>{{ p.updated_at ?? '—' }}</dd>
                            <dt>Photos</dt><dd>{{ p.gallery.length ? p.gallery.length : 'None uploaded' }}</dd>
                        </dl>
                    </div>

                    <!-- SEO -->
                    <div class="pd-card">
                        <div class="pd-card__title">SEO</div>
                        <dl v-if="seoRows.length" class="pd-seo mb-0">
                            <template v-for="[key, label] in seoRows" :key="key"><dt>{{ label }}</dt><dd>{{ p.seo[key] }}</dd></template>
                        </dl>
                        <div v-else class="portal-muted small">No custom SEO set — defaults are generated from the listing.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <CrmModal :open="lightbox !== null" size="xl" bare content-class="bg-dark border-0" @close="lightbox = null">
            <template #header>
                <div class="modal-header border-0 py-2">
                    <span class="text-white small">{{ (lightbox ?? 0) + 1 }} / {{ photos.length }}</span>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close" @click="lightbox = null"></button>
                </div>
            </template>
            <div class="modal-body p-0">
                <div class="carousel slide">
                    <div class="carousel-inner">
                        <div v-for="(url, i) in photos" :key="i" class="carousel-item" :class="{ active: i === lightbox }">
                            <img :src="url" class="d-block w-100" style="max-height: 75vh; object-fit: contain;" alt="">
                        </div>
                    </div>
                    <template v-if="photos.length > 1">
                        <button class="carousel-control-prev" type="button" @click="step(-1)"><span class="carousel-control-prev-icon"></span></button>
                        <button class="carousel-control-next" type="button" @click="step(1)"><span class="carousel-control-next-icon"></span></button>
                    </template>
                </div>
            </div>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * A listing's detail page (resources/views/portal/properties/show) — gallery + lightbox, headline
 * facts, description per language, details, amenities, floor plans, location, agent, info and SEO.
 * GET /properties/{id}; a listing opened through the other menu's URL moves to its own menu.
 */
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { sectionFor } from './sections';

const props = defineProps({
    id: { type: Number, required: true },
});

const route = useRoute();
const router = useRouter();
const p = ref(null);
const loadError = ref('');
const langTab = ref(null);
const lightbox = ref(null);
const richEls = reactive({});
const collapsed = reactive({});
const clipped = reactive({});

const section = computed(() => sectionFor(p.value?.segment ?? route.meta.segment));
// Same placeholder the listing cards use, so an image-less listing still gets a proper hero.
const photos = computed(() => (p.value.gallery.length ? p.value.gallery : ['https://placehold.co/1600x400?text=No+Image']));
const hasMap = computed(() => !!(p.value.latitude && p.value.longitude));
const SEO_LABELS = [['meta_title', 'Meta Title'], ['meta_description', 'Meta Description'], ['meta_keywords', 'Keywords'], ['og_title', 'OG Title'], ['og_description', 'OG Description'], ['canonical_url', 'Canonical URL'], ['robots', 'Robots']];
const seoRows = computed(() => SEO_LABELS.filter(([key]) => p.value.seo[key]));

function openLightbox(i) {
    lightbox.value = i;
}
function step(delta) {
    lightbox.value = (lightbox.value + delta + photos.value.length) % photos.value.length;
}

// "Read more" only for descriptions long enough to be clipped (measured once the tab is shown).
function measure() {
    nextTick(() => {
        const el = richEls[langTab.value];
        if (!el || clipped[langTab.value] !== undefined) return;
        clipped[langTab.value] = el.scrollHeight > el.clientHeight + 10;
        if (!clipped[langTab.value]) collapsed[langTab.value] = false;
    });
}
watch(langTab, measure);

function load() {
    p.value = null;
    loadError.value = '';
    http.get(`/properties/${props.id}`)
        .then((res) => {
            const data = res.data;
            // A listing opened through the other menu's URL goes to its own menu.
            const own = sectionFor(data.segment);
            if (own.routes.show !== route.name) {
                router.replace({ name: own.routes.show, params: { id: data.id } });
                return;
            }
            p.value = data;
            langTab.value = data.languages[0]?.code ?? null;
            document.title = `${data.title || 'Property'} - Partner Portal`;
            measure();
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

// Also when moved to the other menu's URL (same id, other route).
watch(() => [props.id, route.name], load, { immediate: true });
</script>
