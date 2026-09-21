<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useBlogListing } from '../composables/useBlogListing';
import { useLanguages } from '../composables/useLanguages';

const { blogListing, fetchBlogListing } = useBlogListing();
const { selectedLanguage } = useLanguages();

const currentPage = ref(1);
const activeCategory = ref('all');

function load() {
    fetchBlogListing(currentPage.value, selectedLanguage.value?.code, activeCategory.value);
}

function selectCategory(key) {
    if (activeCategory.value === key) return;
    activeCategory.value = key;
    currentPage.value = 1;
    load();
}

onMounted(load);
watch(selectedLanguage, () => { currentPage.value = 1; load(); });

// The blog cards' reveal-on-scroll animation is wired up by the legacy assets/js/script.js,
// which only scans the DOM once — once the async fetch replaces the empty grid with real
// cards, those freshly rendered elements need that binding run again (window.MWRealty.refresh
// is idempotent, safe to call repeatedly), or they stay stuck at opacity:0 forever.
watch(blogListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

function goToPage(page) {
    if (page < 1 || page > (pagination.value?.last_page || 1) || page === currentPage.value) return;
    currentPage.value = page;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const pageTitle = computed(() => blogListing.value?.title || 'Blog');
const heading = computed(() => blogListing.value?.heading || 'Latest Articles');
const description = computed(() => blogListing.value?.description || '');
const categories = computed(() => blogListing.value?.categories || []);
// "All" needs to stay in this same keyed list (not a separate static button) so Vue's list
// diffing keeps it in place once the real categories arrive — the legacy pill-tabs dropdown
// script (see the reveal-on-scroll comment above) restructures this DOM on first scan while
// "All" is still the only button, and inserts new items relative to wherever "All" physically
// ends up, which shoves it to the end if it isn't part of the same v-for.
const pillOptions = computed(() => [{ key: 'all', label: 'All' }, ...categories.value]);
const pagination = computed(() => blogListing.value?.pagination || null);
// The category filter is applied server-side (see fetchBlogListing/selectCategory) so this is
// already the correct, correctly-paginated set for whichever category is currently selected —
// no client-side re-filtering needed (or wanted: it would silently miss matches on other pages).
const posts = computed(() => blogListing.value?.posts || []);

function postMeta(post) {
    return [post.author_name, post.published_at, post.read_time].filter(Boolean).join(' · ');
}

const pageNumbers = computed(() => {
    if (!pagination.value) return [];
    const { last_page: lastPage } = pagination.value;
    return Array.from({ length: lastPage }, (_, i) => i + 1);
});
</script>

<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ pageTitle }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ pageTitle }}</span></p>
                </div>
            </nav>
        </section>

        <section v-if="blogListing" class="mw-blog-filter">
            <div class="container-ctn">
                <div class="mw-pill-tabs">
                    <button v-for="opt in pillOptions" :key="opt.key" type="button" data-tab :class="{ 'is-active': activeCategory === opt.key }" @click="selectCategory(opt.key)">{{ opt.label }}</button>
                </div>
            </div>
        </section>

        <section class="mw-blog">
            <div class="container-ctn">
                <div class="mw-blog__head">
                    <h2 class="mw-blog__title" data-reveal>{{ heading }}</h2>
                    <p v-if="description" class="mw-blog__text">{{ description }}</p>
                </div>

                <div class="mw-blog__grid" id="blog-grid">
                    <article v-for="(post, index) in posts" :key="post.slug" class="mw-blog-card" data-reveal :style="{ '--reveal-delay': index % 3 }">
                        <router-link :to="`/blog-details/${post.slug}`" class="mw-blog-card__media">
                            <img :src="post.image_url" :alt="post.image_alt || post.title">
                            <span v-if="post.category_label" class="mw-blog-card__tag">{{ post.category_label }}</span>
                        </router-link>
                        <div class="mw-blog-card__body">
                            <p class="mw-blog-card__meta">{{ postMeta(post) }}</p>
                            <h3 class="mw-blog-card__title"><router-link :to="`/blog-details/${post.slug}`">{{ post.title }}</router-link></h3>
                            <p class="mw-blog-card__excerpt">{{ post.excerpt }}</p>
                            <router-link :to="`/blog-details/${post.slug}`" class="mw-blog-card__link">Read More</router-link>
                        </div>
                    </article>
                </div>

                <nav v-if="pagination && pagination.last_page > 1" class="mw-blog__pagination" aria-label="Blog pagination">
                    <button type="button" class="mw-blog__page mw-blog__page--prev" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</button>
                    <button v-for="page in pageNumbers" :key="page" type="button" class="mw-blog__page" :class="{ 'is-active': page === currentPage }" @click="goToPage(page)">{{ page }}</button>
                    <button type="button" class="mw-blog__page mw-blog__page--next" :disabled="currentPage === pagination.last_page" @click="goToPage(currentPage + 1)">Next</button>
                </nav>
            </div>
        </section>
    </main>
</template>
