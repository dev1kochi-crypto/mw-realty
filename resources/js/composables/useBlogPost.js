import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const blogPost = ref(null);
const notFound = ref(false);

function fetchBlogPost(slug, langCode) {
    notFound.value = false;
    return window.axios.get(`/api/blogs/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            blogPost.value = res.data;
            useDocumentHead().setSeo(res.data.post?.seo);
        })
        .catch(() => {
            blogPost.value = null;
            notFound.value = true;
        });
}

/** GET /api/blogs/{slug} -> Api\BlogController::show -> BlogPageService::getPostData. */
export function useBlogPost() {
    return { blogPost, notFound, fetchBlogPost };
}
