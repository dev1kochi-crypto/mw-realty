<?php

namespace App\Services\Seo;

use CMS\SiteManager\Models\CmsKit\SiteInformation;
use CMS\SiteManager\Services\LlmsTxtService;

/**
 * llms.txt (https://llmstxt.org) for this site: a markdown index of the public pages with a
 * one-line description each, grouped by section. Built from the same page list as the sitemap
 * (SiteSitemapService::pages()) — the package version only kept pages that have a CMS Metadata
 * row, which left this site's file nearly empty. Bound over the package's LlmsTxtService.
 */
class SiteLlmsTxtService extends LlmsTxtService
{
    public function __construct(private SiteSitemapService $sitemap)
    {
    }

    public function generate($model = null, bool $isDeletion = false): void
    {
        file_put_contents($this->path(), $this->build($this->sitemap->pages()));
    }

    public function build(array $pages): string
    {
        $site = SiteInformation::first();
        $name = $site?->company_name ?: config('app.name', 'Website');
        $summary = $this->homeMetaDescription()
            ?: "{$name} is a real estate marketplace for buying, selling and renting property in the UAE — listings from verified agents and agencies, market insights and guides.";

        $lines = ["# {$name}", '', "> {$summary}", ''];

        $sections = collect($pages)->groupBy('section');
        $order = ['Home', 'Main pages', 'Properties', 'Agencies', 'Agents', 'Market Insights', 'Blog', 'Careers', 'Campaigns'];
        $sections = $sections->sortBy(fn ($_, $key) => ($i = array_search($key, $order, true)) === false ? 99 : $i);

        foreach ($sections as $section => $items) {
            // A capped section (config 'llms_limit') keeps its most recently updated pages.
            $limit = $items->first()['llms_limit'] ?? null;
            $more = $items->first()['llms_more'] ?? null;
            $total = $items->count();
            if ($limit && $total > $limit) {
                $items = $items->sortByDesc(fn ($p) => $p['lastmod']?->getTimestamp() ?? 0)->take($limit);
            }

            $lines[] = "## {$section}";
            $lines[] = '';
            foreach ($items as $page) {
                $title = str_replace(['[', ']'], ['\[', '\]'], $page['title']);
                $lines[] = "- [{$title}]({$page['url']})" . ($page['description'] !== '' ? ": {$page['description']}" : '');
            }
            if ($limit && $total > $limit && $more) {
                $lines[] = "- [{$more[1]}]({$more[0]}): {$total} listings in total — the {$limit} most recently updated are listed above.";
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }
}
