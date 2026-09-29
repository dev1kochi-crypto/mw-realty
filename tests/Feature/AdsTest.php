<?php

namespace Tests\Feature;

use App\Models\CmsKit\Ad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Admin › Ads: optional overlay text per language, site-path links, and the public API that every ad block reads. */
class AdsTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_admin_saves_ad_text_and_the_api_serves_it_per_language(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        foreach (['ads.view', 'ads.create', 'ads.edit'] as $name) {
            $admin->givePermissionTo(Permission::findOrCreate($name, 'cms'));
        }

        $this->signIn($admin, 'cms')->get(route('cms.ads.create'))->assertOk()->assertSee('Text on the ad');

        $this->post(route('cms.ads.store'), [
            'name' => 'Sidebar Sell',
            'placement' => 'property-details',
            'link_url' => '/#post-property',
            'image' => UploadedFile::fake()->image('ad.jpg', 900, 520),
            'status' => 'on',
            'translations' => [
                'en' => ['eyebrow' => 'MW Realty', 'title' => 'Sell Your Property Faster', 'text' => 'Reach verified buyers.'],
                'ar' => ['eyebrow' => '', 'title' => 'بِع عقارك بشكل أسرع', 'text' => ''],
            ],
        ])->assertRedirect(route('cms.ads.index'));

        $ad = Ad::where('name', 'Sidebar Sell')->firstOrFail();
        $this->assertSame('Sell Your Property Faster', $ad->translations['en']['title']);
        $this->assertArrayNotHasKey('eyebrow', $ad->translations['ar'], 'empty values are dropped');

        $this->getJson('/api/ads?placement=property-details&lang=en')->assertOk()
            ->assertJsonPath('title', 'Sell Your Property Faster')->assertJsonPath('eyebrow', 'MW Realty')->assertJsonPath('link_url', '/#post-property');
        // Arabic title, and the missing Arabic fields fall back to English.
        $this->getJson('/api/ads?placement=property-details&lang=ar')->assertOk()
            ->assertJsonPath('title', 'بِع عقارك بشكل أسرع')->assertJsonPath('eyebrow', 'MW Realty');

        // A placement with nothing running → false (the ad block hides itself).
        $this->assertSame('false', $this->getJson('/api/ads?placement=agents')->getContent());

        // Agents / agencies upload their profile photo / logo from the CRM profile.
        $agent = $this->independentAgent();
        $this->app['auth']->forgetGuards();
        $this->signIn($agent)->get('/portal/profile')->assertOk()->assertSee('avatarInput', false);
        $this->postJson('/portal/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 300, 300)])->assertOk()->assertJsonStructure(['avatar_url']);
        $this->assertNotNull($agent->fresh()->avatar);
        $this->postJson('/portal/profile/avatar', ['avatar' => UploadedFile::fake()->image('tiny.png', 40, 40)])->assertStatus(422);
        $this->deleteJson('/portal/profile/avatar')->assertOk();
        $this->assertNull($agent->fresh()->avatar);
        $this->app['auth']->forgetGuards();
        $this->signIn($admin, 'cms');

        // Switched off → hidden.
        $ad->update(['status' => false]);
        $this->assertSame('false', $this->getJson('/api/ads?placement=property-details')->getContent());
    }
}
