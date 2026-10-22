<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Services\Seo\SiteLlmsTxtService;
use App\Services\Seo\SiteSitemapService;
use CMS\SiteManager\Services\LlmsTxtService;
use CMS\SiteManager\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** sitemap.xml + llms.txt built from the database (config/cms/sitemap.php). */
class SitemapLlmsTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch the real public/sitemap.xml from a test.
        $this->publicDir = sys_get_temp_dir() . '/mw-seo-' . uniqid();
        mkdir($this->publicDir);
        $this->app->usePublicPath($this->publicDir);
        config(['app.url' => 'https://example.test']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->publicDir . '/*') ?: []);
        @rmdir($this->publicDir);
        parent::tearDown();
    }

    private function listing(string $slug, bool $live, string $title = 'Listing'): Property
    {
        return Property::create([
            'slug' => $slug, 'reference_no' => uniqid('P'), 'status' => $live, 'segment' => 'residential', 'listing_type' => 'sale', 'price' => 1,
            'translations' => ['en' => ['title' => $title, 'description' => '<p>Sea <b>view</b> villa with pool.</p>']],
        ]);
    }

    public function test_package_services_resolve_to_the_site_versions(): void
    {
        $this->assertInstanceOf(SiteSitemapService::class, app(SitemapService::class));
        $this->assertInstanceOf(SiteLlmsTxtService::class, app(LlmsTxtService::class));
    }

    public function test_lists_only_publicly_visible_pages(): void
    {
        $this->listing('live-villa', true, 'Live Villa');
        $this->listing('hidden-villa', false);
        $this->independentAgent(['slug' => 'approved-agent', 'name' => 'Approved Agent']);
        $this->independentAgent(['slug' => 'pending-agent', 'status' => 'pending']);
        $this->agency(['slug' => 'good-agency', 'company_name' => 'Good Agency']);

        app(SitemapService::class)->generate();
        $xml = file_get_contents(public_path('sitemap.xml'));

        $this->assertStringContainsString('<loc>https://example.test/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/property-details/live-villa</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/agent-details/approved-agent</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.test/agency-details/good-agency</loc>', $xml);
        $this->assertStringNotContainsString('hidden-villa', $xml);
        $this->assertStringNotContainsString('pending-agent', $xml);
        $this->assertStringNotContainsString('/login', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'valid XML');

        app(LlmsTxtService::class)->generate();
        $llms = file_get_contents(public_path('llms.txt'));
        $this->assertStringStartsWith('# ', $llms);
        $this->assertStringContainsString("## Properties\n\n- [Live Villa](https://example.test/property-details/live-villa): Sea view villa with pool.", $llms);
        $this->assertStringContainsString('- [Good Agency](https://example.test/agency-details/good-agency)', $llms);
        $this->assertStringNotContainsString('hidden-villa', $llms);
    }

    public function test_llms_caps_large_sections_with_a_see_all_link(): void
    {
        config(['cms.sitemap.sources.properties.llms_limit' => 2]);
        foreach (range(1, 3) as $i) {
            $this->listing("villa-{$i}", true, "Villa {$i}");
        }

        app(LlmsTxtService::class)->generate();
        $llms = file_get_contents(public_path('llms.txt'));

        $this->assertSame(2, substr_count($llms, '](https://example.test/property-details/'));
        $this->assertStringContainsString('- [All properties for sale & rent](https://example.test/properties): 3 listings in total', $llms);
        // The sitemap still has all of them.
        app(SitemapService::class)->generate();
        $this->assertSame(3, substr_count(file_get_contents(public_path('sitemap.xml')), '/property-details/'));
    }

    public function test_changing_a_listing_queues_a_rebuild(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['cms.sitemap.auto_regenerate' => true]); // off suite-wide (phpunit.xml)
        $this->listing('queued-villa', true);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RegenerateSeoFiles::class);

        config(['cms.sitemap.auto_regenerate' => false]);
        \Illuminate\Support\Facades\Queue::fake();
        $this->listing('quiet-villa', true);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }
}
