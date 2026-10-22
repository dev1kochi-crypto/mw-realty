import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const blogPost = ref(null);
const notFound = ref(false);
let loadedKey = null;

/**
 * kind 'blog' → /api/blogs/{slug}; 'landing' → /api/landing-pages/{slug} (a "Blog design" landing
 * page, same payload shape). Skips the request when that exact post is already loaded — the
 * catch-all route probes a landing page before BlogDetails mounts and asks for it again.
 */
function fetchBlogPost(slug, langCode, kind = 'blog') {
    const key = `${kind}:${slug}:${langCode || ''}`;
    if (key === loadedKey && blogPost.value) {
        notFound.value = false;
        return Promise.resolve(blogPost.value);
    }
    notFound.value = false;
    const endpoint = kind === 'landing' ? 'landing-pages' : 'blogs';
    return window.axios.get(`/api/${endpoint}/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            blogPost.value = res.data;
            loadedKey = key;
            useDocumentHead().setSeo(res.data.post?.seo);
            return res.data;
        })
        .catch(() => {
            blogPost.value = null;
            loadedKey = null;
            notFound.value = true;
            return null;
        });
}

/** GET /api/blogs/{slug} -> Api\BlogController::show -> BlogPageService::getPostData. */
export function useBlogPost() {
    return { blogPost, notFound, fetchBlogPost };
}
