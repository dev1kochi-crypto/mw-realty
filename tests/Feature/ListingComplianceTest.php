<?php

namespace Tests\Feature;

use App\Mail\ListingComplianceMail;
use App\Mail\ListingReviewRequestedMail;
use App\Models\CmsKit\SiteInformation;
use App\Models\Property;
use App\Notifications\ListingComplianceNotification;
use App\Notifications\ListingReviewRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Dubai listing compliance: DLD permit + Form A, Super Admin approval, re-review, expiry. */
class ListingComplianceTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('kyc');
        config(['services.cloudinary.url' => null, 'cloudinary.cloud_url' => null]);
    }

    private function permitPayload(array $overrides = []): array
    {
        return $this->propertyPayload(array_merge([
            'permit_number' => '7112345678',
            'permit_expires_at' => now()->addMonths(3)->toDateString(),
            'permit_qr' => UploadedFile::fake()->image('qr.png', 200, 200),
            'authorization_type' => 'exclusive',
            'authorization_document' => UploadedFile::fake()->create('form-a.pdf', 20, 'application/pdf'),
        ], $overrides));
    }

    public function test_a_listing_goes_live_only_after_permit_and_admin_approval(): void
    {
        Notification::fake();
        Mail::fake();
        SiteInformation::create(['receipt_email' => 'ops@example.test']);
        $agency = $this->agency();
        $admin = $this->superAdmin();

        // No permit: saved as a draft and kept off the website even with "Active" ticked.
        $this->signIn($agency)->post('/portal/properties', $this->propertyPayload())->assertRedirect()->assertSessionHasNoErrors();
        $property = Property::latest('id')->firstOrFail();
        $this->assertSame(Property::COMPLIANCE_DRAFT, $property->compliance_status);
        $this->assertFalse($property->status);
        $this->postJson("/portal/properties/{$property->id}/toggle-status")->assertStatus(422);

        // Permit + QR + Form A: submitted to Super Admin, still offline.
        $this->put("/portal/properties/{$property->id}", $this->permitPayload(['slug' => $property->slug]))->assertRedirect()->assertSessionHasNoErrors();
        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_PENDING, $property->compliance_status);
        $this->assertFalse($property->status);
        Storage::disk('kyc')->assertExists($property->authorization_document);
        Notification::assertSentTo($admin, ListingReviewRequestedNotification::class);
        Mail::assertQueued(ListingReviewRequestedMail::class, fn ($m) => $m->hasTo('ops@example.test'));
        Notification::assertSentTo($agency, ListingComplianceNotification::class, fn ($n) => $n->event === 'submitted');
        Mail::assertQueued(ListingComplianceMail::class, fn ($m) => $m->event === 'submitted' && $m->hasTo($agency->email));

        // Agencies can't reach the approval queue.
        $this->get('/portal/listing-approvals')->assertForbidden();

        // Super Admin approves: live, and the public page carries the permit.
        \Illuminate\Support\Facades\Auth::guard('portal')->logout();
        $this->signIn($admin, 'cms');
        $this->get('/portal/listing-approvals')->assertOk()->assertSee('7112345678');
        $this->get("/portal/listing-approvals/{$property->id}")->assertOk();
        $this->post("/portal/listing-approvals/{$property->id}/approve")->assertRedirect();
        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_APPROVED, $property->compliance_status);
        $this->assertTrue($property->status);
        Notification::assertSentTo($agency, ListingComplianceNotification::class, fn ($n) => $n->event === 'approved');
        Mail::assertQueued(ListingComplianceMail::class, fn ($m) => $m->event === 'approved');
        $this->getJson("/api/properties/{$property->slug}")->assertOk()->assertJsonPath('permit.number', '7112345678');
        $this->assertSame(3, $property->complianceLogs()->count());
    }

    public function test_changing_the_advertised_price_sends_an_approved_listing_back_for_review(): void
    {
        $agency = $this->agency();
        $this->signIn($agency)->post('/portal/properties', $this->permitPayload())->assertSessionHasNoErrors();
        $property = Property::latest('id')->firstOrFail();
        $property->update(['compliance_status' => Property::COMPLIANCE_APPROVED, 'status' => true]);

        // A description-only edit keeps it live.
        $payload = $this->propertyPayload(['slug' => $property->slug, 'permit_number' => '7112345678', 'permit_expires_at' => $property->permit_expires_at->toDateString(), 'authorization_type' => 'exclusive']);
        $payload['translations']['en']['description'] = 'Now with a new kitchen';
        $this->put("/portal/properties/{$property->id}", $payload)->assertSessionHasNoErrors();
        $this->assertTrue($property->fresh()->status);

        $this->put("/portal/properties/{$property->id}", array_merge($payload, ['price' => 1250000]))->assertSessionHasNoErrors();
        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_PENDING, $property->compliance_status);
        $this->assertFalse($property->status);
    }

    public function test_request_changes_takes_the_listing_down_with_a_note(): void
    {
        $agency = $this->agency();
        $property = $this->property($agency);

        $this->signIn($this->superAdmin(), 'cms');
        $this->post("/portal/listing-approvals/{$property->id}/request-changes", [])->assertSessionHasErrors('note');
        $this->post("/portal/listing-approvals/{$property->id}/request-changes", ['note' => 'Permit is for unit 1204, ad says 1402'])->assertRedirect();

        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_CHANGES_REQUESTED, $property->compliance_status);
        $this->assertFalse($property->status);
        $this->assertSame('Permit is for unit 1204, ad says 1402', $property->compliance_note);
    }

    public function test_the_agency_sees_requested_changes_on_its_listings_and_by_email(): void
    {
        Mail::fake();
        $agency = $this->agency();
        $sentBack = $this->property($agency, null, ['translations' => ['en' => ['title' => 'Marina Loft']]]);
        $live = $this->property($agency, null, ['translations' => ['en' => ['title' => 'Palm Villa']]]);

        $this->signIn($this->superAdmin(), 'cms')
            ->post("/portal/listing-approvals/{$sentBack->id}/request-changes", ['note' => 'Upload the signed Form A'])->assertRedirect();
        Mail::assertQueued(ListingComplianceMail::class, fn ($m) => $m->event === 'changes_requested' && $m->note === 'Upload the signed Form A' && $m->hasTo($agency->email));
        $this->get('/portal/listing-approvals?tab=changes_requested')->assertOk()->assertSee('Marina Loft')->assertDontSee('Palm Villa');

        \Illuminate\Support\Facades\Auth::guard('cms')->logout();
        $this->signIn($agency);
        // Status strip, the note on the card, the sidebar badge, and the filter.
        $this->get('/portal/properties')->assertOk()
            ->assertSee('DLD permit review')->assertSee('Upload the signed Form A')->assertSee('title="Listings that need changes or a renewed DLD permit"', false);
        $this->get('/portal/properties?review=changes_requested')->assertOk()->assertSee('Marina Loft')->assertDontSee('Palm Villa');
        $this->get("/portal/properties/{$sentBack->id}/edit")->assertOk()->assertSee('Note from MW Realty');
    }

    public function test_expired_permits_are_unpublished_by_the_daily_command(): void
    {
        Notification::fake();
        $agency = $this->agency();
        $expired = $this->property($agency, null, ['permit_number' => 'A1', 'permit_expires_at' => now()->subDay()->toDateString()]);
        $valid = $this->property($agency, null, ['permit_number' => 'A2', 'permit_expires_at' => now()->addDays(3)->toDateString()]);

        $this->artisan('properties:expire-permits')->assertSuccessful();

        $this->assertSame(Property::COMPLIANCE_EXPIRED, $expired->fresh()->compliance_status);
        $this->assertFalse($expired->fresh()->status);
        $this->assertTrue($valid->fresh()->status);
        $this->assertNotNull($valid->fresh()->permit_expiry_notified_at, 'reminded within 7 days of expiry');
        Notification::assertSentTo($agency, ListingComplianceNotification::class, fn ($n) => $n->event === 'expired');

        // Bulk "Activate" skips the expired listing.
        $this->signIn($agency)->postJson('/portal/properties/bulk-action', ['action' => 'active', 'ids' => [$expired->id]])->assertJsonPath('affected', 0);
    }

    public function test_one_permit_number_cannot_be_used_on_two_listings(): void
    {
        $agency = $this->agency();
        $this->property($agency, null, ['permit_number' => '7112345678']);

        $this->signIn($agency)->post('/portal/properties', $this->permitPayload())->assertSessionHasErrors('permit_number');
    }
}
