<?php

namespace App\Jobs;

use App\Services\Seo\SiteLlmsTxtService;
use App\Services\Seo\SiteSitemapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Rebuilds public/sitemap.xml and public/llms.txt. Queued when a listed model changes
 * (config/cms/sitemap.php 'regenerate_on_change'); unique, so a burst of saves = one rebuild.
 */
class RegenerateSeoFiles implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 60;

    public function handle(SiteSitemapService $sitemap, SiteLlmsTxtService $llms): void
    {
        $sitemap->generate();
        $llms->generate();
    }
}
