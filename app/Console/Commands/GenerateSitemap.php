<?php

namespace App\Console\Commands;

use App\Models\CmsKit\Blog;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Writes public/sitemap.xml and public/robots.txt directly from this app's own routes +
 * published content.
 *
 * The vendored cms-kit package ships its own admin "Generate Sitemap" button
 * (CMS\SiteManager\Services\SitemapService::fullCrawl()), but that works by *crawling* the site's
 * server-rendered HTML — which finds nothing here, since this is a client-rendered Vue SPA with an
 * empty <div id="app"> on the wire (see SpaController) and no server-rendered <a> links to follow.
 * This command builds the sitemap directly from known static routes and real DB slugs instead, and
 * writes to the exact same public_path('sitemap.xml') the admin's Sitemap page already reads/edits,
 * so nothing else needs to change — just don't use that page's "Generate" button, which would
 * overwrite this with an empty one (note left in that section's admin view + here).
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Regenerate public/sitemap.xml and public/robots.txt from real static pages and published content';

    /** page_key => frontend path, for the static pages that have a real SPA route (see routes/web.php).
     *  'residential'/'developments' are deliberately omitted — they exist as Metadata rows for future
     *  use but have no corresponding route yet. */
    private const STATIC_PAGES = [
        'home' => '/',
        'about' => '/about',
        'properties' => '/properties',
        'commercial' => '/commercial',
        'agents' => '/agents',
        'agencies' => '/agencies',
        'blog' => '/blogs',
        'contact' => '/contact',
        'terms' => '/terms-and-conditions',
        'privacy' => '/privacy-policy',
        'security' => '/security-policy',
        'cookie' => '/cookie-settings',
    ];

    public function handle(): int
    {
        $sitemap = Sitemap::create();

        foreach (self::STATIC_PAGES as $path) {
            $sitemap->add(
                Url::create($path)
                    ->setPriority($path === '/' ? 1.0 : 0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );
        }

        Property::where('status', true)->pluck('slug')->each(
            fn ($slug) => $sitemap->add(
                Url::create("/property-details/{$slug}")
                    ->setPriority(0.7)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            )
        );

        Blog::where('status', true)->pluck('slug')->each(
            fn ($slug) => $sitemap->add(
                Url::create("/blog-details/{$slug}")
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            )
        );

        PortalUser::where('type', 'agent')->where('status', 'approved')->where('is_active', true)
            ->pluck('slug')->each(
                fn ($slug) => $sitemap->add(Url::create("/agent-details/{$slug}")->setPriority(0.5))
            );

        PortalUser::where('type', 'company')->where('status', 'approved')->where('is_active', true)
            ->pluck('slug')->each(
                fn ($slug) => $sitemap->add(Url::create("/agency-details/{$slug}")->setPriority(0.5))
            );

        $sitemap->writeToFile(public_path('sitemap.xml'));
        $this->info('Wrote ' . count($sitemap->getTags()) . ' URLs to public/sitemap.xml');

        $this->writeRobotsTxt();
        $this->info('Wrote public/robots.txt (Sitemap: line points at ' . url('sitemap.xml') . ')');

        return self::SUCCESS;
    }

    /**
     * robots.txt is a static file (same reason sitemap.xml is — see class docblock), so its
     * "Sitemap:" line can't be templated per-request like a normal Blade view; it's baked in here
     * using url(), which follows APP_URL, so regenerating after changing that config keeps it
     * correct. Also keeps admin/portal/auth pages out of the index — nothing there is meant to
     * be found via search.
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
