<script setup>
const posts = [
    {
        category: "investment",
        tag: "Investment",
        image: "/frontend/assets/images/home/project-card-1.jpg",
        alt: "Dubai off-plan apartment towers at sunset",
        meta: "By Sarah Ahmed · Mar 12, 2026 · 6 min read",
        title: "5 Reasons to Invest in Dubai Off-Plan Properties in 2026",
        excerpt: "Off-plan investment continues to be one of the most attractive routes into the Dubai property market — here's why savvy investors are paying attention this year.",
        delay: 0,
    },
    {
        category: "buying-guide",
        tag: "Buying Guide",
        image: "/frontend/assets/images/home/realty-card-1.jpg",
        alt: "Couple reviewing documents with a real estate agent",
        meta: "By Omar Khalid · Mar 08, 2026 · 8 min read",
        title: "A Complete Guide to Buying Property in Dubai as a Foreigner",
        excerpt: "From freehold zones to mortgage eligibility, everything international buyers need to know before purchasing their first Dubai home.",
        delay: 1,
    },
    {
        category: "market-insights",
        tag: "Market Insights",
        image: "/frontend/assets/images/home/place-dubai.jpg",
        alt: "Aerial view of a growing Dubai community",
        meta: "By Fatima Noor · Mar 02, 2026 · 5 min read",
        title: "Top 10 Up-and-Coming Communities in Dubai for 2026",
        excerpt: "From Dubai South to Jumeirah Village Circle, these are the neighborhoods delivering the strongest rental yields this year.",
        delay: 2,
    },
    {
        category: "buying-guide",
        tag: "Buying Guide",
        image: "/frontend/assets/images/home/realty-card-2.jpg",
        alt: "Modern apartment building facade",
        meta: "By Omar Khalid · Feb 24, 2026 · 4 min read",
        title: "Freehold vs Leasehold: What Every Dubai Buyer Should Know",
        excerpt: "Understanding the difference between freehold and leasehold ownership is the first step to a confident property purchase.",
        delay: 0,
    },
    {
        category: "market-insights",
        tag: "Market Insights",
        image: "/frontend/assets/images/home/luxury-card-1.jpg",
        alt: "Luxury villa with private pool",
        meta: "By Sarah Ahmed · Feb 18, 2026 · 7 min read",
        title: "How the Golden Visa Is Reshaping Dubai's Real Estate Market",
        excerpt: "Long-term residency incentives are drawing a new wave of investors into premium Dubai neighborhoods — here's the impact so far.",
        delay: 1,
    },
    {
        category: "advice",
        tag: "Advice",
        image: "/frontend/assets/images/home/project-card-3.jpg",
        alt: "Keys handed over for a new rental apartment",
        meta: "By Fatima Noor · Feb 10, 2026 · 5 min read",
        title: "Renting vs Buying in Dubai: Which Is Right for You?",
        excerpt: "A breakdown of the costs, flexibility, and long-term value of renting compared to buying in today's Dubai market.",
        delay: 2,
    },
    {
        category: "advice",
        tag: "Advice",
        image: "/frontend/assets/images/home/realty-card-3.jpg",
        alt: "Apartment building service charge documents",
        meta: "By Omar Khalid · Feb 03, 2026 · 4 min read",
        title: "Understanding Service Charges in Dubai Real Estate",
        excerpt: "What service charges actually cover, how they're calculated, and questions to ask before you buy in a managed community.",
        delay: 0,
    },
    {
        category: "market-insights",
        tag: "Market Insights",
        image: "/frontend/assets/images/home/luxury-card-2.jpg",
        alt: "Dubai Marina skyline at dusk",
        meta: "By Sarah Ahmed · Jan 27, 2026 · 6 min read",
        title: "Dubai Marina vs Downtown Dubai: Where Should You Live?",
        excerpt: "Two of Dubai's most iconic addresses compared — lifestyle, price per square foot, and rental demand side by side.",
        delay: 1,
    },
];
</script>

<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Blog</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>Blog</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-blog-filter">
            <div class="container-ctn">
                <div class="mw-pill-tabs" data-tab-group data-filter-target="#blog-grid">
                    <button type="button" class="is-active" data-tab data-filter="all">All</button>
                    <button type="button" data-tab data-filter="investment">Investment</button>
                    <button type="button" data-tab data-filter="buying-guide">Buying Guide</button>
                    <button type="button" data-tab data-filter="market-insights">Market Insights</button>
                    <button type="button" data-tab data-filter="advice">Advice</button>
                </div>
            </div>
        </section>

        <section class="mw-blog">
            <div class="container-ctn">
                <div class="mw-blog__head">
                    <h2 class="mw-blog__title" data-reveal>Latest Articles</h2>
                </div>

                <div class="mw-blog__grid" id="blog-grid">
                    <article v-for="post in posts" :key="post.title" class="mw-blog-card" :data-category="post.category" data-reveal :style="post.delay ? { '--reveal-delay': post.delay } : undefined">
                        <router-link to="/blog-details" class="mw-blog-card__media">
                            <img :src="post.image" :alt="post.alt">
                            <span class="mw-blog-card__tag">{{ post.tag }}</span>
                        </router-link>
                        <div class="mw-blog-card__body">
                            <p class="mw-blog-card__meta">{{ post.meta }}</p>
                            <h3 class="mw-blog-card__title"><router-link to="/blog-details">{{ post.title }}</router-link></h3>
                            <p class="mw-blog-card__excerpt">{{ post.excerpt }}</p>
                            <router-link to="/blog-details" class="mw-blog-card__link">Read More</router-link>
                        </div>
                    </article>
                </div>

                <nav class="mw-blog__pagination" aria-label="Blog pagination">
                    <button type="button" class="mw-blog__page mw-blog__page--prev">Previous</button>
                    <button type="button" class="mw-blog__page is-active">1</button>
                    <button type="button" class="mw-blog__page">2</button>
                    <button type="button" class="mw-blog__page">3</button>
                    <span class="mw-blog__page mw-blog__page--ellipsis" aria-hidden="true">…</span>
                    <button type="button" class="mw-blog__page">6</button>
                    <button type="button" class="mw-blog__page mw-blog__page--next">Next</button>
                </nav>
            </div>
        </section>
    </main>
</template>
