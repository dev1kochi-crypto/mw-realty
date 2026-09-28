<?php

namespace App\Services;

use App\Mail\NewsletterContentUpdate;
use App\Models\CmsKit\Blog;
use App\Models\CmsKit\MarketInsight;
use App\Models\CmsKit\NewsletterSignup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterCampaignService
{
    /** Send any items that became public after their scheduled publish date. */
    public function sendDueContent(): void
    {
        Blog::where('status', true)
            ->whereDate('published_at', '<=', today())
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('newsletter_campaigns')
                    ->where('content_type', 'blog')->whereColumn('content_id', 'blogs.id');
            })->orderBy('id')->chunkById(100, fn ($posts) => $posts->each(fn (Blog $post) => $this->publishBlog($post)));

        MarketInsight::where('status', true)
            ->whereDate('published_at', '<=', today())
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('newsletter_campaigns')
                    ->where('content_type', 'market-insight')->whereColumn('content_id', 'market_insights.id');
            })->orderBy('id')->chunkById(100, fn ($posts) => $posts->each(fn (MarketInsight $post) => $this->publishInsight($post)));
    }

    public function publishBlog(Blog $blog): void
    {
        if (!$blog->status || !$blog->published_at || $blog->published_at->isFuture()) {
            return;
        }

        $this->sendOnce(
            'blog',
            $blog->id,
            $blog->getTranslation('title', 'en') ?: 'New article',
            strip_tags((string) ($blog->getTranslation('description', 'en') ?: $blog->getTranslation('content', 'en'))),
            url('/blog-details/' . $blog->slug),
            $blog->feature_image ? media_url($blog->feature_image) : null,
        );
    }

    public function publishInsight(MarketInsight $insight): void
    {
        if (!$insight->status || !$insight->published_at || $insight->published_at->isFuture()) {
            return;
        }

        $this->sendOnce(
            'market-insight',
            $insight->id,
            $insight->getTranslation('title', 'en') ?: 'New market insight',
            strip_tags((string) ($insight->getTranslation('summary', 'en') ?: $insight->getTranslation('content', 'en'))),
            url('/market-insights/' . $insight->slug),
            $insight->detail_image ? media_url($insight->detail_image) : ($insight->card_image ? media_url($insight->card_image) : null),
        );
    }

    private function sendOnce(string $type, int $contentId, string $title, string $summary, string $url, ?string $image): void
    {
        $now = now();
        $inserted = DB::table('newsletter_campaigns')->insertOrIgnore([
            'content_type' => $type,
            'content_id' => $contentId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$inserted) {
            return;
        }

        try {
            NewsletterSignup::where('is_subscribed', true)
                ->whereNotNull('unsubscribe_token')
                ->orderBy('id')
                ->chunkById(100, function ($subscribers) use ($type, $title, $summary, $url, $image) {
                    foreach ($subscribers as $subscriber) {
                        Mail::to($subscriber->email)->queue(new NewsletterContentUpdate(
                            $type === 'blog' ? 'New blog article' : 'New market insight',
                            $title,
                            mb_substr(trim($summary), 0, 600),
                            $url,
                            $image,
                            route('newsletter.unsubscribe', $subscriber->unsubscribe_token),
                        ));
                    }
                });

            DB::table('newsletter_campaigns')
                ->where('content_type', $type)
                ->where('content_id', $contentId)
                ->update(['sent_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('newsletter_campaigns')
                ->where('content_type', $type)
                ->where('content_id', $contentId)
                ->delete();
            Log::error('Newsletter campaign queue failed.', [
                'content_type' => $type,
                'content_id' => $contentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
