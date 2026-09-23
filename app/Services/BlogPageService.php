<?php

namespace App\Services;

use App\Models\CmsKit\Blog;
use App\Models\CmsKit\BlogCategory;
use App\Models\CmsKit\SectionLabel;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;

/** Builds the payloads for the /blogs listing page and the /blog-details/{slug} page. */
class BlogPageService
{
    private const CACHE_TTL = 180; // seconds

    public function getListingData(string $lang, int $page = 1, int $perPage = 12, ?string $category = null): array
    {
        $cacheKey = "blogs-listing:{$lang}:{$page}:{$perPage}:" . ($category ?: 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $page, $perPage, $category) {
            $section = SectionLabel::where('section_key', 'blogs')->where('status', true)->first();
            $categories = $this->categoryLabels($lang);
            // Category filtering runs server-side, inside the same paginated query — not as a
            // client-side filter of one already-fetched page — so the pagination totals/pages
            // stay correct for whichever category (or "all") is currently selected.
            $paginator = Blog::where('status', true)
                ->when($category, fn ($query) => $query->whereJsonContains('extra_fields->category', $category))
                ->orderBy('order_index')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'title' => $section?->getTranslation('listing_title', $lang) ?: 'Blog',
                'heading' => $section?->getTranslation('title', $lang) ?: 'Latest Articles',
                'description' => $section?->getTranslation('description', $lang),
                'categories' => $this->categoryOptions($categories),
                'active_category' => $category,
                'posts' => collect($paginator->items())->map(fn ($post) => $this->mapCard($post, $lang, $categories))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
                'seo' => SeoMeta::forStaticPage('blog', $lang),
            ];
        });
    }

    public function getPostData(string $lang, string $slug): ?array
    {
        return Cache::remember("blog-post:{$lang}:{$slug}", self::CACHE_TTL, function () use ($lang, $slug) {
            $post = Blog::where('status', true)->where('slug', $slug)->first();
            if (!$post) {
                return null;
            }

            $categories = $this->categoryLabels($lang);
            $previous = Blog::where('status', true)->where('order_index', '<', $post->order_index)->orderBy('order_index', 'desc')->first();
            $next = Blog::where('status', true)->where('order_index', '>', $post->order_index)->orderBy('order_index')->first();
            $recent = Blog::where('status', true)->where('id', '!=', $post->id)->orderBy('published_at', 'desc')->take(5)->get();

            return [
                'post' => $this->mapDetail($post, $lang, $categories),
                'categories' => $this->categoryOptions($categories),
                'previous' => $previous ? ['slug' => $previous->slug, 'title' => $previous->getTranslation('title', $lang)] : null,
                'next' => $next ? ['slug' => $next->slug, 'title' => $next->getTranslation('title', $lang)] : null,
                'recent' => $recent->map(fn ($p) => [
                    'slug' => $p->slug,
                    'title' => $p->getTranslation('title', $lang),
                    'image_url' => $p->feature_image ? asset('storage/'.$p->feature_image) : null,
                    'published_at' => $p->published_at?->format('M d, Y'),
                ])->values(),
            ];
        });
    }

    /** slug => translated title, for every active blog category. */
    private function categoryLabels(string $lang): array
    {
        return BlogCategory::active()->orderBy('order_index')->get()
            ->mapWithKeys(fn ($category) => [$category->slug => $category->getTranslation('title', $lang)])
            ->all();
    }

    private function categoryOptions(array $categories): array
    {
        return collect($categories)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    private function mapCard(Blog $post, string $lang, array $categories): array
    {
        $extra = $post->extra_fields ?? [];
        $category = $extra['category'] ?? null;

        return [
            'slug' => $post->slug,
            'category' => $category,
            'category_label' => $category ? ($categories[$category] ?? $category) : null,
            'image_url' => $post->feature_image ? asset('storage/'.$post->feature_image) : null,
            'image_alt' => $post->feature_image_alt,
            'title' => $post->getTranslation('title', $lang),
            'excerpt' => data_get($post->translations, "{$lang}.extra_fields.excerpt"),
            'author_name' => $extra['author_name'] ?? null,
            'read_time' => $this->calculateReadTime($post->getTranslation('content', $lang)),
            'published_at' => $post->published_at?->format('M d, Y'),
        ];
    }

    /** "X min read", estimated from the post's word count at ~200 words/minute — never stored, always live. */
    private function calculateReadTime(?string $content): string
    {
        $wordCount = str_word_count(strip_tags((string) $content));
        $minutes = max(1, (int) ceil($wordCount / 200));

        return "{$minutes} min read";
    }

    private function mapDetail(Blog $post, string $lang, array $categories): array
    {
        $extra = $post->extra_fields ?? [];
        $category = $extra['category'] ?? null;

        return [
            'slug' => $post->slug,
            'category' => $category,
            'category_label' => $category ? ($categories[$category] ?? $category) : null,
            'title' => $post->getTranslation('title', $lang),
            'content' => $post->getTranslation('content', $lang),
            'excerpt' => data_get($post->translations, "{$lang}.extra_fields.excerpt"),
            'cover_image_url' => $post->detail_image ? asset('storage/'.$post->detail_image) : ($post->feature_image ? asset('storage/'.$post->feature_image) : null),
            'cover_image_alt' => $post->detail_image_alt ?: $post->feature_image_alt,
            'author_name' => $extra['author_name'] ?? null,
            'author_role' => $extra['author_role'] ?? null,
            'author_avatar_url' => !empty($extra['author_avatar']) ? asset('storage/'.$extra['author_avatar']) : null,
            'read_time' => $this->calculateReadTime($post->getTranslation('content', $lang)),
            'published_at' => $post->published_at?->format('F d, Y'),
            'seo' => SeoMeta::resolve($post->metadata, $post->seoFallback($lang)),
        ];
    }
}
