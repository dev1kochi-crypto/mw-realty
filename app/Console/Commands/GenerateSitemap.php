<?php

namespace App\Console\Commands;

use App\Services\Seo\SiteLlmsTxtService;
use App\Services\Seo\SiteSitemapService;
use Illuminate\Console\Command;

/**
 * Writes public/sitemap.xml, public/llms.txt and public/robots.txt from this app's static pages
 * and published content. The sitemap / llms.txt themselves come from SiteSitemapService /
 * SiteLlmsTxtService (configured in config/cms/sitemap.php) — the same code the CMS "Generate"
 * buttons and the on-save rebuild use. The package's own crawler can't be used: this is a
 * client-rendered Vue SPA, so there are no server-rendered links to follow.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Regenerate public/sitemap.xml, public/llms.txt and public/robots.txt from real static pages and published content';

    public function handle(SiteSitemapService $sitemap, SiteLlmsTxtService $llms): int
    {
        $sitemap->generate();
        $this->info('Wrote ' . substr_count((string) file_get_contents(public_path('sitemap.xml')), '<url>') . ' URLs to public/sitemap.xml');

        $llms->generate();
        $this->info('Wrote public/llms.txt');

        $this->writeRobotsTxt();
        $this->info('Wrote public/robots.txt (Sitemap: line points at ' . url('sitemap.xml') . ')');

        return self::SUCCESS;
    }

    /**
     * robots.txt is a static file, so its "Sitemap:" line is baked in using url() (follows
     * APP_URL — regenerate after changing it). Also keeps admin/portal/auth pages out of the index.
     */
    private function writeRobotsTxt(): void
    {
        $disallow = ['/admin', '/portal', '/login', '/signup', '/verify-email', '/forgot-password', '/reset-password', '/agent-login', '/agent-signup', '/agency-login', '/agency-signup', '/profile', '/thank-you'];

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('sitemap.xml');

        file_put_contents(public_path('robots.txt'), implode("\n", $lines) . "\n");
    }
}
