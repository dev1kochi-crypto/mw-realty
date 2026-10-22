<?php

namespace Tests\Feature;

use App\Models\Property;
use CMS\SiteManager\Models\CmsKit\UrlRedirect;
use CMS\SiteManager\Services\UrlRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Slug-change redirects never loop or chain; manual rules can't create loops. */
class UrlRedirectSafetyTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    private function rules(): array
    {
        return UrlRedirect::orderBy('old_path')->pluck('new_url', 'old_path')->all();
    }

    private function svc(): UrlRedirectService
    {
        return app(UrlRedirectService::class);
    }

    public function test_renaming_back_and_forth_never_loops(): void
    {
        $this->svc()->recordSlugChange('blog', 'a', 'b', null);
        $this->assertSame(['/blog-details/a' => '/blog-details/b'], $this->rules());

        // Back to "a": "a" is live again, so its rule goes; only b → a remains.
        $this->svc()->recordSlugChange('blog', 'b', 'a', null);
        $this->assertSame(['/blog-details/b' => '/blog-details/a'], $this->rules());
    }

    public function test_successive_renames_point_straight_at_the_latest_url(): void
    {
        $this->svc()->recordSlugChange('career', 'a', 'b', null);
        $this->svc()->recordSlugChange('career', 'b', 'c', null);
        $this->svc()->recordSlugChange('career', 'c', 'd', null);

        $this->assertSame([
            '/careers/a' => '/careers/d',
            '/careers/b' => '/careers/d',
            '/careers/c' => '/careers/d',
        ], $this->rules());
    }

    public function test_property_and_agent_slug_edits_redirect_automatically(): void
    {
        $property = Property::create(['slug' => 'old-villa', 'reference_no' => 'R1', 'status' => true, 'segment' => 'residential', 'listing_type' => 'sale', 'price' => 1]);
        $property->update(['slug' => 'new-villa']);
        $agent = $this->independentAgent(['slug' => 'old-agent']);
        $agent->update(['slug' => 'new-agent']);
        $agency = $this->agency(['slug' => 'old-agency']);
        $agency->update(['slug' => 'new-agency']);

        $this->assertSame([
            '/agency-details/old-agency' => '/agency-details/new-agency',
            '/agent-details/old-agent' => '/agent-details/new-agent',
            '/property-details/old-villa' => '/property-details/new-villa',
        ], $this->rules());

        // Deleting the listing: both its URLs now go to /properties (still a single hop).
        $property->delete();
        $this->assertSame('/properties', $this->rules()['/property-details/new-villa']);
        $this->assertSame('/properties', $this->rules()['/property-details/old-villa']);
    }

    public function test_redirect_middleware_follows_a_single_hop(): void
    {
        $this->svc()->recordSlugChange('blog', 'a', 'b', null);
        $this->svc()->recordSlugChange('blog', 'b', 'c', null);

        // The package only registers its middleware outside the console, so call what it calls.
        $response = $this->svc()->tryRedirect(\Illuminate\Http\Request::create('/blog-details/a'));
        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/blog-details/c', $response->headers->get('Location'));
        $this->assertNull($this->svc()->tryRedirect(\Illuminate\Http\Request::create('/blog-details/c')), 'the live page is not redirected');
    }

    public function test_manual_rules_that_loop_or_point_at_themselves_are_refused(): void
    {
        $this->svc()->upsertRedirect('/x', '/y', 301, null, 'manual');

        foreach ([['/z', '/z'], ['/y', '/x']] as [$from, $to]) {
            try {
                $this->svc()->upsertRedirect($from, $to, 301, null, 'manual');
                $this->fail("{$from} → {$to} should be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('new_url', $e->errors());
            }
        }
        $this->assertSame(['/x' => '/y'], $this->rules());

        // A manual rule to /x is fine — and /x's own target becomes the hop-free destination for it.
        $this->svc()->upsertRedirect('/w', '/x', 301, null, 'manual');
        $this->svc()->upsertRedirect('/x', '/v', 301, null, 'manual');
        $this->assertSame('/v', $this->rules()['/w']);
    }

    public function test_admin_edit_screen_blocks_a_loop(): void
    {
        $this->svc()->upsertRedirect('/x', '/y', 301, null, 'manual');
        $other = $this->svc()->upsertRedirect('/q', '/r', 301, null, 'manual');

        $admin = $this->superAdmin();
        foreach (['url-redirects.view', 'url-redirects.edit'] as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name, 'cms');
            $admin->givePermissionTo($name);
        }
        $as = $this->actingAs($admin, 'cms')->withSession(['password_hash_cms' => $admin->getAuthPassword()]);

        // Editing /q → /r into /y → /x would loop with /x → /y.
        $as->put(route('cms.url-redirects.update', $other->id), ['old_path' => '/y', 'new_url' => '/x', 'status_code' => 301, 'is_active' => 1])
            ->assertSessionHasErrors('new_url');
        $this->assertSame(['/q' => '/r', '/x' => '/y'], $this->rules());

        // Self-redirect is refused too; a normal edit goes through.
        $as->put(route('cms.url-redirects.update', $other->id), ['old_path' => '/q', 'new_url' => '/q', 'status_code' => 301, 'is_active' => 1])
            ->assertSessionHasErrors('new_url');
        $as->put(route('cms.url-redirects.update', $other->id), ['old_path' => '/q', 'new_url' => '/s', 'status_code' => 301, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('/s', $other->fresh()->new_url);
    }
}
