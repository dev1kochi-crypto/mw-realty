<?php

namespace App\Services;

use App\Models\CmsKit\MarketInsight;
use App\Models\CmsKit\MarketInsightTerm;
use App\Models\CmsKit\SectionLabel;
use App\Support\SeoMeta;
use Illuminate\Support\Facades\Cache;

/** Builds the payloads for the /market-insights listing page and the /market-insights/{slug} page. */
class MarketInsightPageService
{
    private const CACHE_TTL = 180; // seconds

    public function getListingData(string $lang, int $page = 1, int $perPage = 9, ?string $topic = null, ?string $region = null): array
    {
        $cacheKey = "market-insights-listing:{$lang}:{$page}:{$perPage}:" . ($topic ?: 'all') . ':' . ($region ?: 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $page, $perPage, $topic, $region) {
            $section = SectionLabel::where('section_key', 'market-insights')->first();
            $filtered = $topic || $region;

            // The featured post leads the unfiltered first page, and is left out of the grid below it
            // so it never appears twice. Any filter or later page is just the plain, paginated grid.
            $featured = (!$filtered && $page === 1)
                ? MarketInsight::active()->where('is_featured', true)->latest('published_at')->first()
                : null;

            $paginator = MarketInsight::active()
                ->when($topic, fn ($q) => $q->where('topic', $topic))
                ->when($region, fn ($q) => $q->where('region', $region))
                ->when($featured, fn ($q) => $q->whereKeyNot($featured->id))
                ->orderBy('order_index')
                ->orderByDesc('published_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'title' => $section?->getTranslation('listing_title', $lang) ?: 'Market Insights',
                'heading' => $section?->getTranslation('title', $lang) ?: 'Research & Reports',
                'description' => $section?->getTranslation('description', $lang),
                'topics' => $this->usedOptions('topic', $lang),
                'regions' => $this->usedOptions('region', $lang),
                'active_topic' => $topic,
                'active_region' => $region,
                'featured' => $featured ? array_merge($this->mapCard($featured, $lang), [
                    'image_url' => $this->imageUrl($featured->featured_image ?: $featured->detail_image ?: $featured->card_image),
                ]) : null,
                'posts' => collect($paginator->items())->map(fn ($post) => $this->mapCard($post, $lang))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
                'seo' => SeoMeta::forStaticPage('market-insights', $lang),
            ];
        });
    }

    public function getPostData(string $lang, string $slug): ?array
    {
        return Cache::remember("market-insight:{$lang}:{$slug}", self::CACHE_TTL, function () use ($lang, $slug) {
            $post = MarketInsight::active()->where('slug', $slug)->first();
            if (!$post) {
                return null;
            }

            $related = MarketInsight::active()->whereKeyNot($post->id)
                ->orderByRaw('topic = ? desc', [$post->topic])
                ->orderByDesc('published_at')
                ->take(3)->get();

            return [
                'post' => array_merge($this->mapCard($post, $lang), [
                    'detail_image_url' => $this->imageUrl($post->detail_image ?: $post->card_image),
                    'content' => $post->getTranslation('content', $lang),
                    'takeaways' => $this->lines($post->getTranslation('takeaways', $lang)),
                    'stats' => $this->mapStats($post, $lang),
                    'author_role' => $post->getTranslation('author_role', $lang),
                    'report_url' => $post->report_file ? media_url($post->report_file) : null,
                    'published_at_long' => $post->published_at?->format('F d, Y'),
                    'seo' => SeoMeta::resolve($post->metadata, $post->seoFallback($lang)),
                ]),
                'related' => $related->map(fn ($other) => $this->mapCard($other, $lang))->values(),
                'topics' => $this->usedOptions('topic', $lang),
            ];
        });
    }

    private function mapCard(MarketInsight $post, string $lang): array
    {
        $stats = $this->mapStats($post, $lang);

        return [
            'slug' => $post->slug,
            'title' => $post->getTranslation('title', $lang),
            'summary' => $post->getTranslation('summary', $lang),
            'topic' => $post->topic,
            'topic_label' => MarketInsightTerm::label('topic', $post->topic, $lang),
            'region' => $post->region,
            'region_label' => MarketInsightTerm::label('region', $post->region, $lang),
            'image_url' => $this->imageUrl($post->card_image ?: $post->detail_image),
            'image_alt' => $post->image_alt,
            'author_name' => $post->getTranslation('author_name', $lang),
            'published_at' => $post->published_at?->format('M d, Y'),
            'read_time' => $this->readTime($post->getTranslation('content', $lang)),
            'lead_stat' => $stats[0] ?? null,
            'has_report' => (bool) $post->report_file,
        ];
    }

    /** Headline figures with their label in $lang (falling back to English). */
    private function mapStats(MarketInsight $post, string $lang): array
    {
        $fallback = config('app.fallback_locale', 'en');

        return collect($post->stats ?? [])
            ->map(fn ($stat) => [
                'value' => $stat['value'] ?? '',
                'trend' => $stat['trend'] ?? 'flat',
                'label' => $stat['label'][$lang] ?? $stat['label'][$fallback] ?? '',
            ])
            ->filter(fn ($stat) => $stat['value'] !== '')
            ->values()
            ->all();
    }

    /** Active topics / regions ($type) that at least one active post uses, in admin order: [{key, label}]. */
    private function usedOptions(string $type, string $lang): array
    {
        $used = MarketInsight::active()->whereNotNull($type)->distinct()->pluck($type);

        return MarketInsightTerm::ofType($type)->where('status', true)->whereIn('slug', $used)->ordered()->get()
            ->map(fn ($term) => ['key' => $term->slug, 'label' => $term->getTranslation('title', $lang)])
            ->values()
            ->all();
    }

    private function imageUrl(?string $path): ?string
    {
        return $path ? media_url($path) : null;
    }

    private function lines(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text))
            ->map(fn ($line) => trim(ltrim(trim($line), '-•*')))
            ->filter()
            ->values()
            ->all();
    }

    /** "X min read", estimated at ~200 words/minute — never stored, always live. */
    private function readTime(?string $content): string
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags((string) $content)), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) ceil($words / 200)) . ' min read';
    }
}
