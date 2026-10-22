<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyFinderConnection;
use App\Models\PropertyFinderImport;
use App\Services\PropertyFinder\PropertyFinderImporter;
use App\Services\PropertyFinder\PropertyFinderReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** CRM › Integrations › Property Finder: connect, import (no duplicates), Super Admin review. */
class PropertyFinderIntegrationTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake([
            '*/v1/auth/token' => Http::response(['accessToken' => 'token']),
            '*/v1/listings*' => Http::response(['data' => [
                $this->listing('PF1'),
                $this->listing('PF2', ['compliance' => ['listingAdvertisementNumber' => 'PERMIT-2']]),
                $this->listing('PF3', ['state' => ['stage' => 'draft']]),
            ], 'pagination' => ['totalPages' => 1]]),
            '*/v1/locations/*' => Http::response(['data' => ['name' => 'Dubai Marina', 'tree' => [['name' => 'Dubai'], ['name' => 'Dubai Marina']], 'coordinates' => ['lat' => 25.08, 'lng' => 55.14]]]),
            '*' => Http::response('', 404), // photos
        ]);
    }

    private function listing(string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id, 'reference' => "REF-{$id}", 'title' => ['en' => "Marina apartment {$id}"],
            'offeringType' => 'sale', 'category' => 'residential', 'type' => 'apartment',
            'price' => ['amounts' => ['sale' => 1500000]], 'bedrooms' => 2, 'bathrooms' => 2, 'size' => 1200,
            'location' => ['id' => 55], 'compliance' => ['listingAdvertisementNumber' => "PERMIT-{$id}-x"],
            'state' => ['stage' => 'live'],
        ], $overrides);
    }

    private function connect($owner): PropertyFinderConnection
    {
        return PropertyFinderConnection::create([
            'portal_user_id' => $owner->id, 'api_key' => 'key-' . $owner->id, 'api_secret' => 'secret',
            'api_key_hash' => PropertyFinderConnection::hashKey('key-' . $owner->id),
        ]);
    }

    public function test_an_agency_connects_after_property_finder_accepts_the_keys(): void
    {
        $agency = $this->agency();

        $this->signIn($agency)->post('/portal/crm/integrations/property-finder', ['api_key' => 'pf-key', 'api_secret' => 'pf-secret'])
            ->assertSessionHas('toast');

        $connection = PropertyFinderConnection::where('portal_user_id', $agency->id)->firstOrFail();
        $this->assertSame('pf-key', $connection->api_key);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/auth/token') && $r['apiKey'] === 'pf-key');
    }

    public function test_agency_agents_see_the_agency_connection_but_cannot_change_it(): void
    {
        $agency = $this->agency();
        $this->connect($agency);
        $agent = $this->memberAgent($agency);

        $this->signIn($agent)->get('/portal/crm/integrations/property-finder')->assertOk()->assertSee('Import all listings');
        $this->post('/portal/crm/integrations/property-finder', ['api_key' => 'x', 'api_secret' => 'y'])->assertForbidden();
        $this->delete('/portal/crm/integrations/property-finder')->assertForbidden();
    }

    public function test_live_listings_are_imported_off_the_website_once(): void
    {
        $agency = $this->agency();
        $connection = $this->connect($agency);
        $importer = app(PropertyFinderImporter::class);

        $this->assertSame([2, 0, 0], $importer->sync($connection, 'all'), 'the draft listing is not imported');
        $this->assertSame([0, 2, 0], $importer->sync($connection->fresh(), 'new'), 'nothing is imported twice');

        $property = Property::where('metadata->property_finder_id', 'PF1')->firstOrFail();
        $this->assertSame($agency->id, $property->portal_user_id);
        $this->assertFalse($property->status);
        $this->assertSame('dubai', $property->emirate);
        $this->assertSame(PropertyFinderImport::PENDING, PropertyFinderImport::where('pf_listing_id', 'PF1')->value('review_status'));
    }

    public function test_a_listing_whose_permit_the_account_already_lists_is_skipped(): void
    {
        $agency = $this->agency();
        $connection = $this->connect($agency);
        app(PropertyFinderImporter::class)->sync($connection, 'all');
        // Same permit as PF2, from another key of the same account.
        Property::where('metadata->property_finder_id', 'PF1')->update(['permit_number' => 'PERMIT-NEW']);
        PropertyFinderImport::query()->delete();

        app(PropertyFinderImporter::class)->sync($connection->fresh(), 'all');

        $this->assertSame(1, Property::where('permit_number', 'PERMIT-2')->count());
    }

    public function test_super_admin_approves_or_rejects_imports(): void
    {
        $agency = $this->agency();
        app(PropertyFinderImporter::class)->sync($this->connect($agency), 'all');
        [$keep, $drop] = PropertyFinderImport::orderBy('id')->get()->all();

        $this->signIn($this->superAdmin(), 'cms')->get('/portal/crm/integrations/property-finder/review')->assertOk()->assertSee('Marina apartment PF1');
        $this->post('/portal/crm/integrations/property-finder/review', ['action' => 'approve', 'ids' => [$keep->id]])->assertSessionHas('toast');
        $this->post('/portal/crm/integrations/property-finder/review', ['action' => 'reject', 'ids' => [$drop->id], 'note' => 'Wrong photos']);

        $this->assertSame(PropertyFinderImport::APPROVED, $keep->fresh()->review_status);
        $this->assertSame(PropertyFinderImport::REJECTED, $drop->fresh()->review_status);
        $this->assertNull(Property::find($drop->property_id), 'a rejected import removes its property');
        $this->assertNotNull(Property::find($keep->property_id));
    }

    public function test_only_super_admin_reviews(): void
    {
        $this->signIn($this->agency())->get('/portal/crm/integrations/property-finder/review')->assertForbidden();
        $this->post('/portal/crm/integrations/property-finder/review', ['action' => 'approve', 'ids' => [1]])->assertForbidden();
    }
}
