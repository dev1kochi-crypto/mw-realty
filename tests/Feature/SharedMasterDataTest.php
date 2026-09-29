<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Master › Stage / Tag / Source: Super Admin's items are global & read-only; accounts add their own; in-use items are cleared before delete. */
class SharedMasterDataTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_super_admin_items_are_shared_and_read_only_for_accounts(): void
    {
        $admin = $this->superAdmin();
        $this->signIn($admin, 'cms')->post('/portal/crm/master/stages', ['name' => 'Global Viewing', 'color' => '#123456'])->assertRedirect();
        $this->post('/portal/crm/master/tags', ['name' => 'Global Tag', 'color' => '#123456'])->assertRedirect();
        $this->post('/portal/crm/master/sources', ['name' => 'Global Source'])->assertRedirect();
        $globalStage = LeadStage::where('name', 'Global Viewing')->firstOrFail();
        $this->assertTrue($globalStage->isGlobal());

        $agency = $this->agency();
        $this->app['auth']->forgetGuards();
        $this->signIn($agency);

        // Visible and usable, marked MW Realty, no edit / delete.
        $this->get('/portal/crm/master/stages')->assertOk()->assertSee('Global Viewing')->assertSee('MW Realty')->assertSee('View only');
        $this->get('/portal/crm/master/tags')->assertOk()->assertSee('Global Tag');
        $this->get('/portal/crm/master/sources')->assertOk()->assertSee('Global Source');
        $this->put("/portal/crm/master/stages/{$globalStage->id}", ['name' => 'Hacked', 'color' => '#000000'])->assertNotFound();
        $this->deleteJson("/portal/crm/master/stages/{$globalStage->id}")->assertNotFound();
        $this->assertSame('Global Viewing', $globalStage->fresh()->name);

        // A lead can use the global stage / tag / source.
        $lead = $this->enquire($this->property($agency), 'Uses Global');
        $this->patchJson("/portal/crm/leads/{$lead->id}/stage", ['stage_id' => $globalStage->id])->assertOk();
        $this->assertSame($globalStage->id, $lead->fresh()->stage_id);

        // Own items: only this agency sees them, and it can edit them.
        $this->post('/portal/crm/master/stages', ['name' => 'My Stage', 'color' => '#abcdef'])->assertRedirect();
        $own = LeadStage::where('name', 'My Stage')->firstOrFail();
        $this->assertSame($agency->id, $own->portal_user_id);
        $this->put("/portal/crm/master/stages/{$own->id}", ['name' => 'My Stage 2', 'color' => '#abcdef'])->assertRedirect();
        $this->assertSame('My Stage 2', $own->fresh()->name);

        $other = $this->agency();
        $this->app['auth']->forgetGuards();
        $this->signIn($other)->get('/portal/crm/master/stages')->assertOk()->assertSee('Global Viewing')->assertDontSee('My Stage 2');
    }

    public function test_in_use_item_is_cleared_from_leads_then_deleted(): void
    {
        $agency = $this->agency();
        $this->signIn($agency);
        $this->post('/portal/crm/master/tags', ['name' => 'Temp Tag', 'color' => '#ff0000'])->assertRedirect();
        $this->post('/portal/crm/master/sources', ['name' => 'Temp Source'])->assertRedirect();
        $tag = LeadTag::where('name', 'Temp Tag')->firstOrFail();
        $source = LeadSource::where('name', 'Temp Source')->firstOrFail();

        $leads = collect(range(1, 3))->map(fn ($i) => $this->enquire($this->property($agency), "Tagged {$i}"));
        $leads->each(function (Lead $lead) use ($tag, $source) {
            $lead->tags()->attach($tag->id);
            $lead->update(['source_id' => $source->id]);
        });

        // Blocked while in use; the list shows the count and the connected leads.
        $this->deleteJson("/portal/crm/master/tags/{$tag->id}")->assertStatus(422)->assertJson(['in_use' => true]);
        $this->get('/portal/crm/master/tags')->assertOk()->assertSee('linked-leads-btn', false);
        $this->getJson("/portal/crm/master/tags/{$tag->id}/leads")->assertOk()->assertJsonPath('total', 3)->assertJsonCount(3, 'results');

        // Remove from one selected lead, then "select all" the rest.
        $this->postJson("/portal/crm/master/tags/{$tag->id}/leads/remove", ['ids' => [$leads[0]->id]])->assertOk()->assertJson(['removed' => 1, 'remaining' => 2]);
        $this->postJson("/portal/crm/master/tags/{$tag->id}/leads/remove", ['all' => true])->assertOk()->assertJson(['removed' => 2, 'remaining' => 0]);
        $this->deleteJson("/portal/crm/master/tags/{$tag->id}")->assertOk();
        $this->assertNull($tag->fresh());

        $this->postJson("/portal/crm/master/sources/{$source->id}/leads/remove", ['all' => true])->assertOk()->assertJson(['removed' => 3]);
        $this->assertNull($leads[1]->fresh()->source_id);
        $this->deleteJson("/portal/crm/master/sources/{$source->id}")->assertOk();

        // Another account can't see or clear this agency's items.
        $mine = LeadStage::create(['portal_user_id' => $agency->id, 'name' => 'Mine', 'color' => '#111111', 'order_index' => 9]);
        $this->app['auth']->forgetGuards();
        $this->signIn($this->agency())->getJson("/portal/crm/master/stages/{$mine->id}/leads")->assertNotFound();
    }
}
