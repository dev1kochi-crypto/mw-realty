<?php

namespace Tests\Feature;

use App\Models\CmsKit\LandingPage;
use App\Models\CmsKit\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Landing pages: "Blog design" (template) type — admin switch, API payload, public rendering. */
class LandingPageBlogDesignTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'status' => true, 'is_default' => true]);
    }

    private function admin()
    {
        $admin = $this->superAdmin();
        foreach (['landing-pages.view', 'landing-pages.create', 'landing-pages.edit'] as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name, 'cms');
            $admin->givePermissionTo($name);
        }

        return $this->actingAs($admin, 'cms')->withSession(['password_hash_cms' => $admin->getAuthPassword()]);
    }

    private function page(array $attributes = []): LandingPage
    {
        return LandingPage::create(array_merge([
            'slug' => 'summer-offer', 'page_type' => LandingPage::TYPE_TEMPLATE, 'status' => true, 'order_index' => 1,
            'translations' => ['en' => ['title' => 'Summer Offer', 'content' => '<p>Big <b>savings</b> on villas.</p>']],
            'published_at' => '2026-10-01', 'use_site_header_footer' => true,
        ], $attributes));
    }

    public function test_create_form_offers_blog_design_and_custom_html(): void
    {
        $this->admin()->get(route('cms.landing-pages.create'))->assertOk()
            ->assertSee('id="typeTemplate"', false)->assertSee('id="typeCustom"', false)
            ->assertSee('Blog design')->assertSee('name="translations[en][content]"', false)
            ->assertSee('name="feature_image"', false);
    }

    public function test_admin_saves_a_blog_design_page(): void
    {
        $this->admin()->post(route('cms.landing-pages.store'), [
            'page_type' => 'template', 'status' => 1, 'published_at' => '2026-10-05', 'feature_image_alt' => 'Pool',
            'translations' => ['en' => ['title' => 'Winter Deals', 'content' => '<p>Hello</p>']],
        ])->assertRedirect(route('cms.landing-pages.index'))->assertSessionHasNoErrors();

        $page = LandingPage::where('slug', 'winter-deals')->firstOrFail();
        $this->assertSame('template', $page->page_type);
        $this->assertSame('<p>Hello</p>', $page->getTranslation('content', 'en'));
        $this->assertSame('Pool', $page->feature_image_alt);
        $this->assertNull($page->custom_html);

        // Content is required for this type.
        $this->admin()->post(route('cms.landing-pages.store'), [
            'page_type' => 'template', 'translations' => ['en' => ['title' => 'Empty', 'content' => '']],
        ])->assertSessionHasErrors('translations.en.content');
    }

    public function test_api_returns_blog_shaped_payload(): void
    {
        $this->page();

        $this->getJson('/api/landing-pages/summer-offer?lang=en')->assertOk()
            ->assertJsonPath('post.title', 'Summer Offer')
            ->assertJsonPath('post.content', '<p>Big <b>savings</b> on villas.</p>')
            ->assertJsonPath('post.published_at', 'October 01, 2026')
            ->assertJsonPath('post.seo.meta_title', 'Summer Offer | MW Realty')
            ->assertJsonPath('previous', null)->assertJsonPath('is_landing_page', true);

        // Custom-HTML pages and inactive pages aren't served by this endpoint.
        $this->page(['slug' => 'custom-one', 'page_type' => LandingPage::TYPE_CUSTOM, 'custom_html' => '<p>x</p>']);
        $this->page(['slug' => 'hidden-one', 'status' => false]);
        $this->getJson('/api/landing-pages/custom-one')->assertNotFound();
        $this->getJson('/api/landing-pages/hidden-one')->assertNotFound();
    }

    public function test_public_url_serves_the_spa_with_seo_tags(): void
    {
        $this->page(['metadata' => ['meta_description' => 'Limited summer villa offers.']]);

        $this->get('/summer-offer')->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('Summer Offer | MW Realty', false)
            ->assertSee('Limited summer villa offers.', false);

        $this->get('/no-such-page')->assertNotFound();
    }
}
