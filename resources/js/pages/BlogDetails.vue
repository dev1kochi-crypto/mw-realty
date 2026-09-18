<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useBlogPost } from '../composables/useBlogPost';
import { useLanguages } from '../composables/useLanguages';

const route = useRoute();
const { blogPost, notFound, fetchBlogPost } = useBlogPost();
const { selectedLanguage } = useLanguages();

function load() {
    fetchBlogPost(route.params.slug, selectedLanguage.value?.code);
}

onMounted(load);
watch(() => route.params.slug, load);
watch(selectedLanguage, load);
watch(
    () => blogPost.value?.post?.title,
    (title) => {
        if (title) document.title = `${title} | MW Realty`;
    },
);

const post = computed(() => blogPost.value?.post || null);
const categories = computed(() => blogPost.value?.categories || []);
const recentPosts = computed(() => blogPost.value?.recent || []);
const previousPost = computed(() => blogPost.value?.previous || null);
const nextPost = computed(() => blogPost.value?.next || null);
</script>

<template>
    <main v-if="post">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ post.title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/blogs">Blog</router-link><span v-if="post.category_label"><span class="mw-about-crumb__sep"> / </span><span>{{ post.category_label }}</span></span></p>
                </div>
            </nav>
        </section>

        <section class="mw-blog-details">
            <div class="container-ctn">
                <div class="mw-blog-details__layout">

                    <article class="mw-blog-details__main">
                        <div class="mw-blog-details__cover">
                            <img :src="post.cover_image_url" :alt="post.cover_image_alt || post.title">
                        </div>

                        <span v-if="post.category_label" class="mw-blog-details__tag">{{ post.category_label }}</span>

                        <div class="mw-blog-details__meta">
                            <div v-if="post.author_name" class="mw-blog-details__author">
                                <span v-if="post.author_avatar_url" class="mw-blog-details__author-avatar">
                                    <img :src="post.author_avatar_url" alt="">
                                </span>
                                <span>
                                    <span class="mw-blog-details__author-name">{{ post.author_name }}</span>
                                    <span v-if="post.author_role" class="mw-blog-details__author-role">{{ post.author_role }}</span>
                                </span>
                            </div>
                            <span v-if="post.published_at" class="mw-blog-details__meta-item">{{ post.published_at }}</span>
                            <span v-if="post.read_time" class="mw-blog-details__meta-item">{{ post.read_time }}</span>
                        </div>

                        <div class="mw-blog-details__content" v-html="post.content"></div>

                        <div class="mw-blog-details__share">
                            <span>Share this article</span>
                            <a href="#" aria-label="Share on Facebook"><img src="/frontend/assets/images/icons/social-facebook.svg" alt=""></a>
                            <a href="#" aria-label="Share on Twitter"><img src="/frontend/assets/images/icons/social-twitter.svg" alt=""></a>
                            <a href="#" aria-label="Share on LinkedIn"><img src="/frontend/assets/images/icons/social-linkedin.svg" alt=""></a>
                            <a href="#" aria-label="Share on Instagram"><img src="/frontend/assets/images/icons/social-instagram.svg" alt=""></a>
                        </div>

                        <nav class="mw-blog-details__pager" aria-label="Post navigation">
                            <router-link v-if="previousPost" :to="`/blog-details/${previousPost.slug}`" class="mw-blog-details__pager-item">
                                <span class="mw-blog-details__pager-dir">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                                    Previous Post
                                </span>
                                <span class="mw-blog-details__pager-title">{{ previousPost.title }}</span>
                            </router-link>
                            <span v-else class="mw-blog-details__pager-item is-disabled" aria-disabled="true">
                                <span class="mw-blog-details__pager-dir">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                                    Previous Post
                                </span>
                                <span class="mw-blog-details__pager-title">You're on the earliest article</span>
                            </span>
                            <router-link v-if="nextPost" :to="`/blog-details/${nextPost.slug}`" class="mw-blog-details__pager-item mw-blog-details__pager-item--next">
                                <span class="mw-blog-details__pager-dir">
                                    Next Post
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                                </span>
                                <span class="mw-blog-details__pager-title">{{ nextPost.title }}</span>
                            </router-link>
                            <span v-else class="mw-blog-details__pager-item mw-blog-details__pager-item--next is-disabled" aria-disabled="true">
                                <span class="mw-blog-details__pager-dir">
                                    Next Post
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                                </span>
                                <span class="mw-blog-details__pager-title">You're on the latest article</span>
                            </span>
                        </nav>
                    </article>

                    <aside class="mw-blog-details__aside" data-sticky-sidebar>
                        <div class="mw-blog-details__aside-panel">
                            <h3 class="mw-blog-details__aside-title">Recent Blogs</h3>
                            <div class="mw-blog-details__recent">
                                <router-link v-for="recent in recentPosts" :key="recent.slug" :to="`/blog-details/${recent.slug}`" class="mw-blog-details__recent-item">
                                    <span class="mw-blog-details__recent-thumb">
                                        <img :src="recent.image_url" alt="">
                                    </span>
                                    <span class="mw-blog-details__recent-content">
                                        <span class="mw-blog-details__recent-title">{{ recent.title }}</span>
                                        <span class="mw-blog-details__recent-date">{{ recent.published_at }}</span>
                                    </span>
                                </router-link>
                            </div>
                        </div>

                        <div class="mw-blog-details__aside-panel">
                            <h3 class="mw-blog-details__aside-title">Categories</h3>
                            <div class="mw-blog-details__categories">
                                <router-link v-for="category in categories" :key="category.key" to="/blogs" class="mw-blog-details__category">{{ category.label }}</router-link>
                            </div>
                        </div>
                    </aside>

                </div>
            </div>
        </section>
    </main>

    <main v-else-if="notFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Post Not Found</h1>
                </div>
            </div>
        </section>
        <section class="mw-blog-details">
            <div class="container-ctn">
                <p>This blog post doesn't exist or has been removed. <router-link to="/blogs">Back to Blog</router-link></p>
            </div>
        </section>
    </main>
</template>
