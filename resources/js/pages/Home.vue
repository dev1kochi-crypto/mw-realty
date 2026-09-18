<script setup>
import { computed, nextTick, watch } from 'vue';
import { useHomePage } from '../composables/useHomePage';

// One fetch for the whole page (see routes/api.php -> Api\HomeController) instead of a
// request per section — every section below just reads its own slice of `homePage.value`.
const { homePage } = useHomePage();

const banner = computed(() => homePage.value?.banner || null);
const brands = computed(() => homePage.value?.brands || []);
const homeAd = computed(() => homePage.value?.ad || null);
const developments = computed(() => homePage.value?.developments || null);
const premiumProperties = computed(() => homePage.value?.premiumProperties || null);
const luxuryProject = computed(() => homePage.value?.luxury || null);
const whyChooseUsSection = computed(() => homePage.value?.whyChooseUs || null);
const realtyProperty = computed(() => homePage.value?.realty || null);
const communitiesSection = computed(() => homePage.value?.communities || null);
const findPropertiesSection = computed(() => homePage.value?.findProperties || null);
const postPropertySection = computed(() => homePage.value?.postProperty || null);
const popularPlacesSection = computed(() => homePage.value?.popularPlaces || null);
const testimonialsSection = computed(() => homePage.value?.testimonials || null);
const contactInfo = computed(() => homePage.value?.contact || null);

const heroLine1 = computed(() => banner.value?.line_1 || 'Your Trusted Partner in');
const heroLine2 = computed(() => banner.value?.line_2 || 'Finding the Best Properties');
const heroVideoUrl = computed(() => banner.value?.video_url || '/frontend/assets/video/banner.mp4');
const heroNoteText = computed(() => banner.value?.note_text || 'Want to find out more about UAE real estate using AI?');
const heroNoteBadge = computed(() => banner.value?.note_badge || 'Comming Soon');
const heroGuideText = computed(() => banner.value?.note_link_text || 'Let Us Guide Your Home');
const heroGuideUrl = computed(() => banner.value?.note_link_url || '#');

const heroTabs = [
    { filter: 'buy', label: 'Buy', active: true },
    { filter: 'rent', label: 'Rent' },
    { filter: 'ready', label: 'Ready' },
    { filter: 'off-plan', label: 'Off-Plan' },
];

const bedroomOptions = [
    { value: 'studio', label: 'Studio' },
    { value: '1', label: '1 Bedroom' },
    { value: '2', label: '2 Bedrooms' },
    { value: '3', label: '3 Bedrooms' },
    { value: '4', label: '4 Bedrooms' },
    { value: '5', label: '5 Bedrooms' },
    { value: '6', label: '6 Bedrooms' },
    { value: '7+', label: '7+ Bedrooms' },
];

const propertyTypeOptions = ['Apartment', 'Villa', 'Penthouse', 'Townhouse', 'Plot', 'Office'];

const pricePresets = [
    { min: 0, max: 500000, label: 'Under 500K' },
    { min: 500000, max: 1000000, label: '500K – 1M' },
    { min: 1000000, max: 2000000, label: '1M – 2M' },
    { min: 2000000, max: 5000000, label: '2M – 5M' },
    { min: 5000000, max: 10000000, label: '5M – 10M' },
    { min: 10000000, max: 30000000, label: '10M+' },
];

const displayLogos = computed(() => brands.value.map((brand) => ({ src: brand.image_url, alt: brand.alt || '' })));

const projectsEyebrow = computed(() => developments.value?.eyebrow || 'UAE developments');
const projectsTitle = computed(() => developments.value?.title || 'Browse New Projects in the UAE');
const projectsButtonLabel = computed(() => developments.value?.button_name || 'View All Properties');

const projectFilters = computed(() => {
    const cities = developments.value?.cities || [];
    return [
        { filter: 'all', label: 'All', active: true },
        ...cities.map((city) => ({ filter: slugify(city), label: city })),
    ];
});

const projectCards = computed(() => {
    const properties = developments.value?.properties || [];
    return properties.map((p) => ({
        category: p.category,
        images: p.images,
        isAbsoluteImage: true,
        name: p.name,
        location: p.location,
        beds: p.beds ?? '—',
        baths: p.baths ?? '—',
        area: p.area ?? '—',
        type: p.type,
        price: p.price,
    }));
});

// Nothing to show until real listings exist — no lorem-ipsum placeholder cards on a live site.
const showDevelopments = computed(() => projectCards.value.length > 0);

function slugify(value) {
    return String(value).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

// The project cards' photo gallery slider and the "All / Dubai / Abu Dhabi ..." tab filter
// are wired up by the legacy assets/js/script.js, which only scans the DOM once — once the
// real data replaces the placeholder cards above, the freshly rendered elements need that
// binding run again (window.MWRealty.refresh is idempotent, safe to call repeatedly).
watch(projectCards, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

const premiumEyebrow = computed(() => premiumProperties.value?.eyebrow || 'Exclusive selection');
const premiumTitle = computed(() => premiumProperties.value?.title || 'Premium Properties');
const premiumDescription = computed(() => premiumProperties.value?.description || 'A highlight of exclusive listings from our premium customers — featured homes selected for exceptional location, quality, and investment value.');
const premiumButtonLabel = computed(() => premiumProperties.value?.button_name || 'View More Details');
const premiumButtonUrl = computed(() => premiumProperties.value?.button_url || '#');

const highlightCards = computed(() => {
    const properties = premiumProperties.value?.properties || [];
    return properties.map((p) => ({
        images: p.images,
        isAbsoluteImage: true,
        name: p.name,
        location: p.location,
        beds: p.beds,
        baths: p.baths,
        area: p.area,
        price: p.price,
    }));
});

// Nothing to show until at least one property is marked Featured — no placeholder villas.
const showPremiumProperties = computed(() => highlightCards.value.length > 0);

watch(highlightCards, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

const postPropertyEyebrow = computed(() => postPropertySection.value?.eyebrow || 'List with MW Realty');
const postPropertyTitle = computed(() => postPropertySection.value?.title || 'Post your property in 3 simple steps');
const postPropertyDescription = computed(() => postPropertySection.value?.description || '');
const postPropertyButtonText = computed(() => postPropertySection.value?.button_text || 'Start listing');
const postPropertyButtonUrl = computed(() => postPropertySection.value?.button_url || '#contact');
const postPropertyImageUrl = computed(() => postPropertySection.value?.image_url || '/frontend/assets/images/home/post-property.jpg');
const postPropertyImageAlt = computed(() => postPropertySection.value?.image_alt || 'Aerial view of a UAE waterfront residential community');

const postPropertySteps = computed(() => {
    const steps = postPropertySection.value?.steps || [];
    return steps.map((s) => ({
        index: s.index,
        iconSrc: s.icon_url,
        title: s.title,
        text: s.description,
    }));
});

const postPropertyBadge = computed(() => `${postPropertySteps.value.length} easy step${postPropertySteps.value.length === 1 ? '' : 's'}`);

// Nothing to show until the admin has added at least one real step — no placeholder steps.
const showPostProperty = computed(() => postPropertySteps.value.length > 0);

const popularPlacesEyebrow = computed(() => popularPlacesSection.value?.eyebrow || 'In-demand cities');
const popularPlacesTitle = computed(() => popularPlacesSection.value?.title || 'Most Popular Properties Places');

const popularPlaces = computed(() => {
    const places = popularPlacesSection.value?.places || [];
    return places.map((p) => ({
        imageSrc: p.image_url,
        name: p.name,
    }));
});

// Nothing to show until the admin has added at least one real place — no placeholder cities.
const showPopularPlaces = computed(() => popularPlaces.value.length > 0);

watch(popularPlaces, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

// --- Luxury Project ---
const luxuryEyebrow = computed(() => luxuryProject.value?.eyebrow || 'Signature collection');
const luxuryTitle = computed(() => luxuryProject.value?.title || 'Luxury Project');
const luxuryDescription = computed(() => luxuryProject.value?.description || '');
const luxuryButtonText = computed(() => luxuryProject.value?.button_text || 'View More Details');
const luxuryButtonUrl = computed(() => luxuryProject.value?.button_url || '#');
const luxuryCards = computed(() => {
    const properties = luxuryProject.value?.properties || [];
    return properties.map((p) => ({
        image: p.images?.[0],
        villa: String(p.type || '').toLowerCase().includes('villa'),
        name: p.name,
        location: p.location,
        stat1: p.stat1,
        stat2: p.stat2,
        area: p.area,
        price: p.price,
    }));
});
const showLuxury = computed(() => luxuryCards.value.length > 0);

// --- Why Choose Us ---
const whyChooseUsEyebrow = computed(() => whyChooseUsSection.value?.eyebrow || 'The MW advantage');
const whyChooseUsTitle = computed(() => whyChooseUsSection.value?.title || 'Why Choose Us');
const whyChooseUsImage = computed(() => whyChooseUsSection.value?.image_url || '/frontend/assets/images/home/why-choose-us.png');
const whyChooseUsImageAlt = computed(() => whyChooseUsSection.value?.image_alt || 'Luxury residential tower developed by MW Realty');
const whyChooseUsItems = computed(() => (whyChooseUsSection.value?.items || []).map((item) => ({
    icon: item.icon_url,
    title: item.title,
    description: item.description,
})));
const showWhyChooseUs = computed(() => whyChooseUsItems.value.length > 0);

// --- Realty Property ---
const realtyEyebrow = computed(() => realtyProperty.value?.eyebrow || 'On-market homes');
const realtyTitle = computed(() => realtyProperty.value?.title || 'Realty Property');
const realtyDescription = computed(() => realtyProperty.value?.description || '');
const realtyButtonText = computed(() => realtyProperty.value?.button_text || 'View More Details');
const realtyButtonUrl = computed(() => realtyProperty.value?.button_url || '#');
const realtyCards = computed(() => {
    const properties = realtyProperty.value?.properties || [];
    return properties.map((p) => ({
        image: p.image,
        name: p.name,
        location: p.location,
        beds: p.beds,
        baths: p.baths,
        area: p.area,
        price: p.price,
    }));
});
const showRealty = computed(() => realtyCards.value.length > 0);

// --- Communities ---
const communityFilters = [
    { filter: 'for-sale', label: 'For Sale', active: true },
    { filter: 'for-rent', label: 'For Rent' },
    { filter: 'off-plan', label: 'Off Plan' },
];
const communitiesEyebrow = computed(() => communitiesSection.value?.eyebrow || 'Neighbourhood living');
const communitiesTitle = computed(() => communitiesSection.value?.title || 'Popular Properties in Dubai Communities');
const communitiesColumns = computed(() => {
    const items = communitiesSection.value?.items || [];
    const columnCount = 4;
    const columns = Array.from({ length: columnCount }, () => []);
    items.forEach((item, i) => {
        columns[i % columnCount].push({ icon: item.icon_url, name: item.name });
    });
    return columns.filter((col) => col.length);
});
const showCommunities = computed(() => (communitiesSection.value?.items || []).length > 0);

// --- Find Properties ---
const findPropertiesModifiers = ['penthouse-1', 'villa', 'penthouse-2', 'plot'];
const findPropertiesEyebrow = computed(() => findPropertiesSection.value?.eyebrow || 'Browse by type');
const findPropertiesTitle = computed(() => findPropertiesSection.value?.title || 'Find Properties');
const findPropertiesCards = computed(() => {
    const items = findPropertiesSection.value?.items || [];
    return items.map((item, i) => ({
        modifier: findPropertiesModifiers[i % findPropertiesModifiers.length],
        image: item.image_url,
        type: item.title,
        count: `${item.count} ${item.count === 1 ? 'property' : 'properties'}`,
    }));
});
const showFindProperties = computed(() => findPropertiesCards.value.length > 0);

// --- Testimonials ---
const testimonialsEyebrow = computed(() => testimonialsSection.value?.eyebrow || 'From our clients');
const testimonialsTitle = computed(() => testimonialsSection.value?.title || 'Why Our Clients Trust Us');
const testimonialsDescription = computed(() => testimonialsSection.value?.description || 'Discover what our customers are saying about their experiences.');
const testimonials = computed(() => testimonialsSection.value?.items || []);
const showTestimonials = computed(() => testimonials.value.length > 0);

// The Luxury/Realty/Testimonials sliders are wired up by the legacy assets/js/script.js,
// which only scans the DOM once — once real data replaces the placeholder cards, the freshly
// rendered elements need that binding run again (window.MWRealty.refresh is idempotent).
watch([luxuryCards, realtyCards, testimonials], () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

// --- Contact ---
const contactEyebrow = computed(() => contactInfo.value?.eyebrow || "We're here to help");
const contactHeading = computed(() => contactInfo.value?.title || 'Connect with the experts who make property management simple.');
const contactText = computed(() => contactInfo.value?.description || 'Experience hassle-free property management, expertly handled by professionals who understand your unique requirements.');
const contactAddress = computed(() => contactInfo.value?.address || 'Office No: 703 - Churchill Tower Business Bay - Dubai, UAE');
const contactTollFree = computed(() => contactInfo.value?.toll_free || '800644489');
const contactWhatsapp = computed(() => contactInfo.value?.whatsapp_number || '971585899990');
const contactEmail = computed(() => contactInfo.value?.email || 'info@mightywarnersrealty.com');
</script>

<template>
    <main>
        <section class="mw-hero">
            <video :key="heroVideoUrl" class="mw-hero__video" autoplay muted loop playsinline poster="/frontend/assets/images/home/hero-bg.jpg" aria-hidden="true">
                <source :src="heroVideoUrl" type="video/mp4">
            </video>
            <div class="container-ctn mw-hero__inner">
                <div class="mw-hero__panel">
                    <h1 class="mw-hero__title">{{ heroLine1 }}</h1>
                    <p class="mw-hero__subtitle">{{ heroLine2 }}</p>

                    <div class="mw-hero__tabs" role="tablist" aria-label="Listing type" data-tab-group>
                        <button v-for="tab in heroTabs" :key="tab.filter" type="button" class="mw-hero__tab" :class="{ 'is-active': tab.active }" data-tab :data-filter="tab.filter">{{ tab.label }}</button>
                    </div>

                    <form class="mw-hero__search" id="search" role="search" @submit.prevent>
                        <label class="mw-hero__field">
                            <img src="/frontend/assets/images/icons/location.svg" alt="">
                            <input type="text" name="location" placeholder="Enter location">
                        </label>
                        <div class="mw-hero__field mw-hero__field--select mw-dropdown" data-dropdown data-dropdown-trigger tabindex="0" role="button" aria-haspopup="listbox">
                            <img src="/frontend/assets/images/icons/bed.svg" alt="">
                            <span data-dropdown-label>Bedroom</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-hero__field-chevron">
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li v-for="opt in bedroomOptions" :key="opt.value"><button type="button" data-dropdown-option :data-value="opt.value">{{ opt.label }}</button></li>
                            </ul>
                        </div>
                        <div class="mw-hero__field mw-hero__field--select mw-hero__price mw-dropdown" data-dropdown>
                            <button type="button" class="mw-hero__price-trigger" data-dropdown-trigger aria-haspopup="dialog">
                                <img src="/frontend/assets/images/icons/tag-price.svg" alt="">
                                <span data-dropdown-label>Price Range</span>
                                <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-hero__field-chevron">
                            </button>
                            <div class="mw-dropdown__menu mw-hero__price-panel" data-dropdown-menu>
                                <div class="mw-hero__price-readout">
                                    <label class="mw-hero__price-input">
                                        <span>Min</span>
                                        <input type="text" inputmode="decimal" data-price-min-input aria-label="Minimum price" value="AED 0">
                                    </label>
                                    <label class="mw-hero__price-input">
                                        <span>Max</span>
                                        <input type="text" inputmode="decimal" data-price-max-input aria-label="Maximum price" value="AED 30M">
                                    </label>
                                </div>
                                <div class="mw-hero__price-slider" data-price-slider>
                                    <div class="mw-hero__price-track"></div>
                                    <div class="mw-hero__price-fill" data-price-range></div>
                                    <input type="range" name="min_price" min="0" max="30000000" step="50000" value="0" data-price-min aria-label="Minimum price">
                                    <input type="range" name="max_price" min="0" max="30000000" step="50000" value="30000000" data-price-max aria-label="Maximum price">
                                </div>
                                <p class="mw-hero__price-presets-title">Quick select</p>
                                <div class="mw-hero__price-presets">
                                    <button v-for="preset in pricePresets" :key="preset.label" type="button" data-price-preset :data-min="preset.min" :data-max="preset.max">{{ preset.label }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="mw-hero__field mw-hero__field--select mw-dropdown" data-dropdown data-dropdown-trigger tabindex="0" role="button" aria-haspopup="listbox">
                            <img src="/frontend/assets/images/icons/building.svg" alt="">
                            <span data-dropdown-label>Property Type</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-hero__field-chevron">
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li v-for="opt in propertyTypeOptions" :key="opt"><button type="button" data-dropdown-option>{{ opt }}</button></li>
                            </ul>
                        </div>
                        <button type="submit" class="mw-hero__submit">Search</button>
                    </form>

                    <div class="mw-hero__foot">
                        <p class="mw-hero__ai-note">{{ heroNoteText }} <strong>{{ heroNoteBadge }}</strong></p>
                        <a :href="heroGuideUrl" class="mw-hero__guide">
                            <span>
                                <img src="/frontend/assets/images/icons/play.svg" alt="" width="24" height="24">
                                {{ heroGuideText }}
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-partners" v-if="displayLogos.length">
            <div class="mw-partners__slider">
                <div class="mw-partners__track">
                    <div class="mw-partners__group">
                        <div v-for="(logo, index) in displayLogos" :key="'a' + index" class="mw-partners__logo"><img :src="logo.src" :alt="logo.alt"></div>
                    </div>
                    <div class="mw-partners__group" aria-hidden="true">
                        <div v-for="(logo, index) in displayLogos" :key="'b' + index" class="mw-partners__logo"><img :src="logo.src" alt=""></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-projects mw-section" id="projects" v-if="showDevelopments">
            <div class="container-ctn">
                <div class="mw-section-head">
                    <p class="mw-eyebrow">{{ projectsEyebrow }}</p>
                    <h2 class="mw-section-title">{{ projectsTitle }}</h2>
                </div>

                <div class="mw-projects__filters">
                    <div class="mw-pill-tabs" data-tab-group data-filter-target="#projects-grid">
                        <button v-for="f in projectFilters" :key="f.filter" type="button" :class="{ 'is-active': f.active }" data-tab :data-filter="f.filter">{{ f.label }}</button>
                    </div>
                </div>

                <div class="mw-projects__grid" id="projects-grid" data-projects-slider>
                    <article v-for="(card, index) in projectCards" :key="index" class="mw-projects__card" :data-category="card.category">
                        <div class="mw-projects__media" data-card-gallery>
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(img, i) in card.images" :key="i" :src="card.isAbsoluteImage ? img : `/frontend/assets/images/home/${img}`" :alt="i === 0 ? card.name : ''" class="mw-projects__photo">
                            </div>
                            <span class="mw-badge mw-badge--success mw-projects__verified">
                                <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                Verified
                            </span>
                            <button type="button" class="mw-projects__save" aria-label="Save property" aria-pressed="false">
                                <img src="/frontend/assets/images/icons/heart.svg" alt="" width="24" height="24">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--prev" data-gallery-prev aria-label="Previous photo">
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--next" data-gallery-next aria-label="Next photo">
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <div class="mw-projects__dots" data-gallery-dots></div>
                            <span class="mw-projects__photo-count">
                                <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                <span data-gallery-count>{{ card.images.length }}</span>
                            </span>
                            <div class="mw-projects__contact">
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-projects__contact-btn mw-projects__contact-btn--email" aria-label="Mail">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="">
                                    <span>Mail</span>
                                </a>
                                <a href="tel:+971585899990" class="mw-projects__contact-btn mw-projects__contact-btn--call" aria-label="Call us">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="">
                                    <span>Call us</span>
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-projects__contact-btn mw-projects__contact-btn--whatsapp" aria-label="Chat with us">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="">
                                    <span>Chat with us</span>
                                </a>
                            </div>
                        </div>
                        <div class="mw-projects__body">
                            <h3 class="mw-projects__name">{{ card.name }}</h3>
                            <p class="mw-projects__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="">
                                {{ card.location }}
                            </p>
                            <div class="mw-projects__divider"></div>
                            <div class="mw-projects__stats">
                                <span class="mw-projects__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="">{{ card.beds }}</span>
                                <span class="mw-projects__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="">{{ card.baths }}</span>
                                <span class="mw-projects__stat"><img src="/frontend/assets/images/icons/area.svg" alt="">{{ card.area }}</span>
                            </div>
                            <div class="mw-projects__divider"></div>
                            <div class="mw-projects__foot-row">
                                <span class="mw-projects__type">{{ card.type }}</span>
                                <span class="mw-projects__price">{{ card.price }}</span>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mw-projects__actions">
                    <router-link to="/properties-dubai" class="mw-btn mw-btn--solid">
                        {{ projectsButtonLabel }}
                        <img src="/frontend/assets/images/icons/arrow-up-right.svg" alt="" class="mw-projects__view-all-icon">
                    </router-link>
                </div>
            </div>
        </section>

        <section class="mw-highlight mw-section" id="premium-properties" v-if="showPremiumProperties">
            <div class="container-ctn">
                <div class="mw-highlight__head">
                    <p class="mw-eyebrow">{{ premiumEyebrow }}</p>
                    <h2 class="mw-section-title">{{ premiumTitle }}</h2>
                    <div class="mw-highlight__head-row">
                        <p class="mw-highlight__text">{{ premiumDescription }}</p>
                        <a :href="premiumButtonUrl" class="mw-btn mw-highlight__cta">
                            {{ premiumButtonLabel }}
                            <img src="/frontend/assets/images/icons/luxury-cta-arrow.svg" alt="" width="18" height="18">
                        </a>
                    </div>
                </div>

                <div class="mw-highlight__carousel">
                    <div class="mw-highlight__track" data-highlight-slider>
                        <article v-for="(card, index) in highlightCards" :key="index" class="mw-highlight__card">
                            <div class="mw-highlight__media" data-card-gallery>
                                <div class="mw-highlight__slides" data-gallery-track>
                                    <img v-for="(img, i) in card.images" :key="i" :src="card.isAbsoluteImage ? img : `/frontend/assets/images/home/${img}`" :alt="i === 0 ? card.name : ''" class="mw-highlight__photo">
                                </div>
                                <span class="mw-highlight__badge">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="18" height="18">
                                    Premium
                                </span>
                                <button type="button" class="mw-highlight__save" aria-label="Save property" aria-pressed="false">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M16.696 3C14.652 3 12.887 4.197 12 5.943C11.113 4.197 9.348 3 7.304 3C4.374 3 2 5.457 2 8.481C2 11.505 3.817 14.277 6.165 16.554C8.513 18.831 12 21 12 21C12 21 15.374 18.867 17.835 16.554C20.46 14.088 22 11.514 22 8.481C22 5.448 19.626 3 16.696 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <button type="button" class="mw-highlight__shot mw-highlight__shot--prev" data-gallery-prev aria-label="Previous photo">
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <button type="button" class="mw-highlight__shot mw-highlight__shot--next" data-gallery-next aria-label="Next photo">
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <div class="mw-highlight__dots" data-gallery-dots></div>
                                <span class="mw-highlight__count">
                                    <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                    <span data-gallery-count>{{ card.images.length }}</span>
                                </span>
                            </div>
                            <div class="mw-highlight__body">
                                <span class="mw-highlight__price">{{ card.price }}</span>
                                <h3 class="mw-highlight__name">{{ card.name }}</h3>
                                <p class="mw-highlight__location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                    {{ card.location }}
                                </p>
                                <div class="mw-highlight__stats">
                                    <span class="mw-highlight__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ card.beds }}</span>
                                    <span class="mw-highlight__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ card.baths }}</span>
                                    <span class="mw-highlight__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">{{ card.area }}</span>
                                </div>
                                <div class="mw-highlight__contacts">
                                    <a href="mailto:info@mightywarnersrealty.com" aria-label="Email"><img src="/frontend/assets/images/icons/realty-email.svg" alt="" width="40" height="40"></a>
                                    <a href="tel:+971585899990" aria-label="Call"><img src="/frontend/assets/images/icons/realty-phone.svg" alt="" width="40" height="40"></a>
                                    <a href="https://wa.me/971585899990" aria-label="WhatsApp"><img src="/frontend/assets/images/icons/realty-whatsapp.svg" alt="" width="40" height="40"></a>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="mw-highlight__nav">
                        <button type="button" data-highlight-prev class="mw-arrow-btn mw-arrow-btn--prev mw-highlight__nav-btn" aria-label="Previous premium property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" data-highlight-next class="mw-arrow-btn mw-arrow-btn--next mw-highlight__nav-btn" aria-label="Next premium property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-home-banner" v-if="homeAd && homeAd.image_url">
            <div class="container-ctn">
                <a :href="homeAd.link_url || '#'" class="mw-home-banner__card" :target="homeAd.link_url ? '_blank' : null" rel="noopener">
                    <span class="mw-home-banner__label">Advertisement</span>
                    <picture>
                        <source v-if="homeAd.mobile_image_url" media="(max-width: 767px)" :srcset="homeAd.mobile_image_url">
                        <img :src="homeAd.image_url" :alt="homeAd.image_alt || homeAd.name" class="mw-home-banner__image">
                    </picture>
                </a>
            </div>
        </section>

        <section class="mw-post-property mw-section mw-section--alt" id="post-property" v-if="showPostProperty">
            <div class="container-ctn">
                <div class="mw-post-property__inner">
                    <div class="mw-post-property__intro">
                        <p class="mw-eyebrow">{{ postPropertyEyebrow }}</p>
                        <h2 class="mw-section-title" v-html="postPropertyTitle"></h2>
                        <p class="mw-post-property__text" v-html="postPropertyDescription"></p>
                        <a :href="postPropertyButtonUrl" class="mw-btn mw-btn--solid mw-post-property__cta">
                            {{ postPropertyButtonText }}
                            <img src="/frontend/assets/images/icons/arrow-up-right.svg" alt="" class="mw-post-property__cta-icon">
                        </a>
                    </div>

                    <div class="mw-post-property__media">
                        <img :src="postPropertyImageUrl" :alt="postPropertyImageAlt" class="mw-post-property__photo">
                        <span class="mw-post-property__media-tag">{{ postPropertyBadge }}</span>
                    </div>

                    <ol class="mw-post-property__steps">
                        <li v-for="step in postPropertySteps" :key="step.index" class="mw-post-property__step">
                            <div class="mw-post-property__card">
                                <span class="mw-post-property__step-index" aria-hidden="true">{{ step.index }}</span>
                                <span class="mw-post-property__step-icon-wrap">
                                    <img :src="step.iconSrc" alt="" class="mw-post-property__step-icon">
                                </span>
                                <div class="mw-post-property__step-body">
                                    <p class="mw-post-property__step-label">Step {{ step.index }}</p>
                                    <h3 class="mw-post-property__step-title">{{ step.title }}</h3>
                                    <p class="mw-post-property__step-text">{{ step.text }}</p>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="mw-popular-places mw-section" id="popular-places" v-if="showPopularPlaces">
            <div class="container-ctn">
                <div class="mw-popular-places__head">
                    <div class="mw-popular-places__heading">
                        <p class="mw-eyebrow">{{ popularPlacesEyebrow }}</p>
                        <h2 class="mw-section-title">{{ popularPlacesTitle }}</h2>
                    </div>
                    <div class="mw-popular-places__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-popular-places__nav-btn" data-places-prev aria-label="Previous places">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="18" height="18">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-popular-places__nav-btn" data-places-next aria-label="Next places">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="18" height="18">
                        </button>
                    </div>
                </div>

                <div class="mw-popular-places__slider" data-places-slider>
                    <a v-for="(place, index) in popularPlaces" :key="index" href="#" class="mw-popular-places__card">
                        <div class="mw-popular-places__media">
                            <img :src="place.imageSrc" :alt="place.name">
                            <div class="mw-popular-places__foot">
                                <span class="mw-popular-places__name">{{ place.name }}</span>
                                <span class="mw-popular-places__arrow">
                                    <img src="/frontend/assets/images/icons/icon-arrow-right.svg" alt="">
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <section class="mw-luxury mw-section" id="luxury" v-if="showLuxury">
            <div class="mw-luxury__mark" aria-hidden="true">
                <img src="/frontend/assets/images/home/luxury-logo.png" alt="">
            </div>
            <div class="container-aside">
                <div class="mw-luxury__layout">
                    <div class="mw-luxury__intro">
                        <p class="mw-eyebrow">{{ luxuryEyebrow }}</p>
                        <h2 class="mw-section-title">{{ luxuryTitle }}</h2>
                        <p class="mw-luxury__text" v-if="luxuryDescription" v-html="luxuryDescription"></p>
                        <div class="mw-luxury__actions">
                            <a :href="luxuryButtonUrl" class="mw-btn mw-luxury__cta">
                                {{ luxuryButtonText }}
                                <img src="/frontend/assets/images/icons/luxury-cta-arrow.svg" alt="" width="18" height="18">
                            </a>
                            <div class="mw-luxury__nav">
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-luxury__nav-btn" data-luxury-prev aria-label="Previous luxury project">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-luxury__nav-btn" data-luxury-next aria-label="Next luxury project">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mw-luxury__stage">
                        <div class="mw-luxury__slider" data-luxury-slider>
                            <article v-for="(card, index) in luxuryCards" :key="index" class="mw-luxury__card">
                                <img :src="card.image" :alt="card.name" class="mw-luxury__photo" :class="{ 'mw-luxury__photo--villa': card.villa }">
                                <span class="mw-luxury__tag">Buy</span>
                                <button type="button" class="mw-luxury__save" aria-label="Save property" aria-pressed="false">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M16.696 3C14.652 3 12.887 4.197 12 5.943C11.113 4.197 9.348 3 7.304 3C4.374 3 2 5.457 2 8.481C2 11.505 3.817 14.277 6.165 16.554C8.513 18.831 12 21 12 21C12 21 15.374 18.867 17.835 16.554C20.46 14.088 22 11.514 22 8.481C22 5.448 19.626 3 16.696 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <div class="mw-luxury__panel">
                                    <h3 class="mw-luxury__name">{{ card.name }}</h3>
                                    <p class="mw-luxury__location">
                                        <img src="/frontend/assets/images/icons/luxury-location.svg" alt="" width="18" height="18">
                                        {{ card.location }}
                                    </p>
                                    <div class="mw-luxury__stats">
                                        <span class="mw-luxury__stat">
                                            <img src="/frontend/assets/images/icons/luxury-home.svg" alt="" width="18" height="18">
                                            {{ card.stat1 }}
                                        </span>
                                        <span class="mw-luxury__stat">
                                            <img src="/frontend/assets/images/icons/luxury-building.svg" alt="" width="18" height="18">
                                            {{ card.stat2 }}
                                        </span>
                                        <span class="mw-luxury__stat">
                                            <img src="/frontend/assets/images/icons/luxury-area.svg" alt="" width="18" height="18">
                                            {{ card.area }}
                                        </span>
                                    </div>
                                    <div class="mw-luxury__foot">
                                        <span class="mw-luxury__price">{{ card.price }}</span>
                                        <div class="mw-luxury__contacts">
                                            <a href="mailto:info@mightywarnersrealty.com" class="mw-luxury__contact" aria-label="Mail">
                                                <img src="/frontend/assets/images/icons/luxury-email.svg" alt="" width="40" height="40">
                                            </a>
                                            <a href="tel:+971585899990" class="mw-luxury__contact" aria-label="Call">
                                                <img src="/frontend/assets/images/icons/luxury-phone.svg" alt="" width="40" height="40">
                                            </a>
                                            <a href="https://wa.me/971585899990" class="mw-luxury__contact" aria-label="WhatsApp">
                                                <img src="/frontend/assets/images/icons/luxury-whatsapp.svg" alt="" width="40" height="40">
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-why-choose-us mw-section" id="why-choose-us" v-if="showWhyChooseUs">
            <div class="container-ctn">
                <div class="mw-why-choose-us__inner">
                    <div class="mw-why-choose-us__visual">
                        <p class="mw-eyebrow">{{ whyChooseUsEyebrow }}</p>
                        <h2 class="mw-section-title">{{ whyChooseUsTitle }}</h2>
                        <div class="mw-why-choose-us__media">
                            <div class="mw-why-choose-us__media-bg">
                                <img src="/frontend/assets/images/home/why-choose-us-bg.png" alt="">
                            </div>
                            <img class="mw-why-choose-us__mark" src="/frontend/assets/images/home/luxury-logo.png" alt="" aria-hidden="true">
                            <div class="mw-why-choose-us__media-photo">
                                <img :src="whyChooseUsImage" :alt="whyChooseUsImageAlt">
                            </div>
                        </div>
                    </div>

                    <div class="mw-why-choose-us__content">
                        <div class="mw-why-choose-us__grid">
                            <div v-for="(item, index) in whyChooseUsItems" :key="index" class="mw-why-choose-us__item">
                                <div class="mw-why-choose-us__item-head">
                                    <img :src="item.icon" alt="" class="mw-why-choose-us__icon">
                                    <h3 class="mw-why-choose-us__title">{{ item.title }}</h3>
                                </div>
                                <p class="mw-why-choose-us__text">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-realty mw-section" id="realty-property" v-if="showRealty">
            <div class="container-ctn">
                <div class="mw-realty__head">
                    <p class="mw-eyebrow">{{ realtyEyebrow }}</p>
                    <h2 class="mw-section-title">{{ realtyTitle }}</h2>
                    <div class="mw-realty__head-row">
                        <p class="mw-realty__text" v-if="realtyDescription" v-html="realtyDescription"></p>
                        <a :href="realtyButtonUrl" class="mw-btn mw-realty__cta">
                            {{ realtyButtonText }}
                            <img src="/frontend/assets/images/icons/luxury-cta-arrow.svg" alt="" width="18" height="18">
                        </a>
                    </div>
                </div>

                <div class="mw-realty__carousel">
                    <div class="mw-realty__track" data-realty-slider>
                        <article v-for="(card, index) in realtyCards" :key="index" class="mw-realty__card">
                            <div class="mw-realty__media">
                                <img :src="card.image" :alt="card.name" class="mw-realty__photo">
                                <span class="mw-badge mw-badge--success mw-realty__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                    Verified
                                </span>
                                <button type="button" class="mw-realty__save" aria-label="Save property" aria-pressed="false">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M16.696 3C14.652 3 12.887 4.197 12 5.943C11.113 4.197 9.348 3 7.304 3C4.374 3 2 5.457 2 8.481C2 11.505 3.817 14.277 6.165 16.554C8.513 18.831 12 21 12 21C12 21 15.374 18.867 17.835 16.554C20.46 14.088 22 11.514 22 8.481C22 5.448 19.626 3 16.696 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="mw-realty__body">
                                <h3 class="mw-realty__name">{{ card.name }}</h3>
                                <p class="mw-realty__location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                    {{ card.location }}
                                </p>
                                <div class="mw-realty__divider"></div>
                                <div class="mw-realty__stats">
                                    <span class="mw-realty__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ card.beds }}</span>
                                    <span class="mw-realty__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ card.baths }}</span>
                                    <span class="mw-realty__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">{{ card.area }}</span>
                                </div>
                                <div class="mw-realty__divider"></div>
                                <div class="mw-realty__foot">
                                    <span class="mw-realty__price">{{ card.price }}</span>
                                    <div class="mw-realty__actions">
                                        <a href="mailto:info@mightywarnersrealty.com" aria-label="Email"><img src="/frontend/assets/images/icons/realty-email.svg" alt="" width="40" height="40"></a>
                                        <a href="tel:+971585899990" aria-label="Call"><img src="/frontend/assets/images/icons/realty-phone.svg" alt="" width="40" height="40"></a>
                                        <a href="https://wa.me/971585899990" aria-label="WhatsApp"><img src="/frontend/assets/images/icons/realty-whatsapp.svg" alt="" width="40" height="40"></a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="mw-realty__nav">
                        <button type="button" data-realty-prev class="mw-arrow-btn mw-arrow-btn--prev mw-realty__nav-btn" aria-label="Previous realty property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" data-realty-next class="mw-arrow-btn mw-arrow-btn--next mw-realty__nav-btn" aria-label="Next realty property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-communities mw-section" id="communities" v-if="showCommunities">
            <div class="container-ctn">
                <div class="mw-section-head">
                    <p class="mw-eyebrow">{{ communitiesEyebrow }}</p>
                    <h2 class="mw-section-title">{{ communitiesTitle }}</h2>
                </div>

                <div class="mw-communities__tabs">
                    <div class="mw-pill-tabs" data-tab-group data-filter-target="#communities-panels">
                        <button v-for="f in communityFilters" :key="f.filter" type="button" :class="{ 'is-active': f.active }" data-tab :data-filter="f.filter">{{ f.label }}</button>
                    </div>
                </div>

                <div id="communities-panels">
                    <div class="mw-communities__grid" data-category="for-sale">
                        <div v-for="(col, ci) in communitiesColumns" :key="ci" class="mw-communities__col">
                            <a v-for="(row, ri) in col" :key="ri" href="#" class="mw-communities__row" :class="{ 'mw-communities__row--last': ri === col.length - 1 }">
                                <img class="mw-communities__icon" :src="row.icon" alt="" width="24" height="24">
                                <span class="mw-communities__name">{{ row.name }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-find-properties mw-section mw-section--alt" id="find-properties" v-if="showFindProperties">
            <div class="container-ctn">
                <div class="mw-section-head">
                    <p class="mw-eyebrow">{{ findPropertiesEyebrow }}</p>
                    <h2 class="mw-section-title">{{ findPropertiesTitle }}</h2>
                </div>

                <div class="mw-find-properties__grid">
                    <a v-for="(card, index) in findPropertiesCards" :key="index" href="#" class="mw-find-properties__card" :class="`mw-find-properties__card--${card.modifier}`">
                        <img :src="card.image" :alt="`${card.type} properties`">
                        <span class="mw-find-properties__label">
                            <span class="mw-find-properties__label-type">{{ card.type }}</span>
                            <span class="mw-find-properties__label-count">{{ card.count }}</span>
                        </span>
                    </a>

                    <div class="mw-find-properties__card mw-find-properties__card--tall">
                        <img src="/frontend/assets/images/home/find-tall.jpg" alt="All properties">
                        <img class="mw-find-properties__mark" src="/frontend/assets/images/home/find-properties-mark.svg" alt="" aria-hidden="true">
                        <router-link to="/properties-dubai" class="mw-find-properties__cta">
                            View All Properties
                            <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-testimonials mw-section" id="testimonials" v-if="showTestimonials">
            <div class="container-ctn">
                <div class="mw-testimonials__header">
                    <div class="mw-section-head">
                        <p class="mw-eyebrow">{{ testimonialsEyebrow }}</p>
                        <h2 class="mw-section-title">{{ testimonialsTitle }}</h2>
                        <p class="mw-section-text">{{ testimonialsDescription }}</p>
                    </div>
                    <div class="mw-testimonials__arrows">
                        <button type="button" data-testimonials-prev class="mw-testimonials__arrow mw-testimonials__arrow--prev" aria-label="Previous testimonial">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" data-testimonials-next class="mw-testimonials__arrow mw-testimonials__arrow--next" aria-label="Next testimonial">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-testimonials__slider" data-testimonials-slider>
                    <template v-for="(item, index) in testimonials" :key="index">
                        <article v-if="item.type === 'video'" class="mw-testimonials__card mw-testimonials__card--video">
                            <div class="mw-testimonials__media">
                                <img class="mw-testimonials__photo" :src="item.image_url" :alt="item.name">
                                <video v-if="item.video_url" class="mw-testimonials__video" :poster="item.image_url" preload="metadata" playsinline>
                                    <source :src="item.video_url">
                                </video>
                                <button type="button" class="mw-testimonials__play" :aria-label="`Play ${item.name}'s video testimonial`">
                                    <img class="mw-testimonials__icon-play" src="/frontend/assets/images/icons/play.svg" alt="" width="24" height="24">
                                    <img class="mw-testimonials__icon-pause" src="/frontend/assets/images/icons/pause.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                            <div class="mw-testimonials__info">
                                <p class="mw-testimonials__name">{{ item.name }}</p>
                                <p class="mw-testimonials__role">{{ item.role }}</p>
                            </div>
                        </article>

                        <article v-else class="mw-testimonials__card mw-testimonials__card--quote">
                            <div class="mw-testimonials__body">
                                <div class="mw-testimonials__stars" :aria-label="`${item.rating || 5} star rating`">
                                    <img v-for="star in (item.rating || 5)" :key="star" src="/frontend/assets/images/icons/star.svg" alt="" width="24" height="24">
                                </div>
                                <img class="mw-testimonials__quote-icon" src="/frontend/assets/images/icons/quote-mark.svg" alt="" width="48" height="48">
                                <p class="mw-testimonials__quote-text">{{ item.content }}</p>
                                <div class="mw-testimonials__author">
                                    <img class="mw-testimonials__avatar" :src="item.image_url" alt="">
                                    <div class="mw-testimonials__author-info">
                                        <p class="mw-testimonials__name mw-testimonials__name--light">{{ item.name }}</p>
                                        <p class="mw-testimonials__role mw-testimonials__role--light">{{ item.role }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="mw-testimonials__nav">
                    <div data-testimonials-dots class="mw-testimonials__dots"></div>
                </div>
            </div>
        </section>

        <section class="mw-contact" id="contact">
            <div class="mw-contact__inner">
                <div class="mw-contact__visual">
                    <div class="mw-contact__photo">
                        <img src="/frontend/assets/images/home/contact-photo.jpg" alt="Modern living room managed by MW Realty">
                    </div>

                    <div class="mw-contact__form-card">
                        <form class="mw-contact__form" novalidate @submit.prevent>
                            <div class="mw-contact__field">
                                <label for="contact-name">Name*</label>
                                <input type="text" id="contact-name" name="name" placeholder="Your name" required>
                            </div>

                            <div class="mw-contact__field">
                                <label for="contact-email">Email*</label>
                                <input type="email" id="contact-email" name="email" placeholder="Your email" required>
                            </div>

                            <div class="mw-contact__field">
                                <label for="contact-phone">phone*</label>
                                <input type="tel" id="contact-phone" name="phone" placeholder="Your phone" required>
                            </div>

                            <div class="mw-contact__field mw-contact__field--select">
                                <label for="contact-interest">what are you interested in?</label>
                                <div class="mw-contact__select-wrap">
                                    <select id="contact-interest" name="interest" required>
                                        <option value="" selected disabled hidden>Select</option>
                                        <option value="buy">Buy a property</option>
                                        <option value="rent">Rent a property</option>
                                        <option value="sell">Sell / list a property</option>
                                        <option value="management">Property management</option>
                                    </select>
                                    <img class="mw-contact__select-chevron" src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="24" height="24">
                                </div>
                            </div>

                            <div class="mw-contact__field">
                                <label for="contact-message">Message</label>
                                <textarea id="contact-message" name="message" rows="5" placeholder="How can we help you?"></textarea>
                            </div>

                            <button type="submit" class="mw-contact__submit">Contact Our Expert</button>
                        </form>
                    </div>
                </div>

                <div class="mw-contact__info">
                    <p class="mw-eyebrow">{{ contactEyebrow }}</p>
                    <h2 class="mw-contact__heading">{{ contactHeading }}</h2>
                    <p class="mw-contact__text">{{ contactText }}</p>

                    <div class="mw-contact__rows">
                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/location.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Office Address</p>
                                <p class="mw-contact__row-value">{{ contactAddress }}</p>
                            </div>
                        </div>

                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Toll Free</p>
                                <a class="mw-contact__row-value mw-contact__row-value--link mw-contact__row-value--lg" :href="`tel:${contactTollFree}`">{{ contactTollFree }}</a>
                            </div>
                        </div>

                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Request A Call Back</p>
                                <a class="mw-contact__row-value mw-contact__row-value--link mw-contact__row-value--lg" :href="`https://wa.me/${contactWhatsapp}`" target="_blank" rel="noopener">+{{ contactWhatsapp }}</a>
                            </div>
                        </div>

                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Email Us</p>
                                <a class="mw-contact__row-value mw-contact__row-value--link" :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
