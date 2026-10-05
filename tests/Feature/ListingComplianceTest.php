<?php

namespace Tests\Feature;

use App\Mail\ListingComplianceMail;
use App\Mail\ListingReviewRequestedMail;
use App\Models\CmsKit\SiteInformation;
use App\Models\PortalUser;
use App\Models\Property;
use App\Notifications\ListingComplianceNotification;
use App\Notifications\ListingReviewRequestedNotification;
use App\Services\ListingComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/**
 * Listing compliance: permit verification (Validate), Super Admin approval where it applies
 * (LISTING_SUPERADMIN_APPROVAL, or a DTCM / None permit), take-down and expiry.
 */
class ListingComplianceTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('kyc');
        config(['services.cloudinary.url' => null, 'cloudinary.cloud_url' => null, 'permits.superadmin_approval' => false]);
    }

    /** A Dubai listing with complete permit details, as the form saves it — then afterSave() as on create. */
    private function savedListing(?PortalUser $owner, array $attributes = []): Property
    {
        $permit = ($attributes['permit_type'] ?? 'rera') === 'none' ? [] : [
            'permit_number' => (string) random_int(1000000000, 9999999999),
            'permit_expires_at' => now()->addMonths(3)->toDateString(),
            'permit_qr' => 'properties/permits/qr.png',
        ];
        $property = $this->property($owner, null, array_merge([
            'emirate' => 'dubai', 'permit_type' => 'rera', 'listing_type' => 'sale',
            'compliance_status' => Property::COMPLIANCE_DRAFT, 'status' => false,
        ], $permit, $attributes));
        app(ListingComplianceService::class)->afterSave($property, null);

        return $property->fresh();
    }

    private function verifiedRera(): array
    {
        return ['permit_type' => 'rera', 'permit_verified_at' => now(), 'permit_verified_via' => 'dld'];
    }

    public function test_with_approval_off_a_verified_permit_goes_live_without_super_admin(): void
    {
        Notification::fake();
        $agency = $this->agency();
        $this->actingAs($agency, 'portal');

        $verified = $this->savedListing($agency, $this->verifiedRera());
        $this->assertSame(Property::COMPLIANCE_APPROVED, $verified->compliance_status);
        $this->assertTrue($verified->canGoLive());

        // Not validated yet: waits for Validate, not for Super Admin.
        $unverified = $this->savedListing($agency, ['permit_type' => 'rera']);
        $this->assertSame(Property::COMPLIANCE_PENDING, $unverified->compliance_status);
        $this->assertFalse($unverified->awaitingApproval());
        $this->assertSame('Not verified', $unverified->complianceLabel());
        Notification::assertNotSentTo($this->superAdmin(), ListingReviewRequestedNotification::class);
    }

    public function test_dtcm_and_none_always_wait_for_super_admin_approval(): void
    {
        Notification::fake();
        Mail::fake();
        SiteInformation::create(['receipt_email' => 'ops@example.test']);
        $admin = $this->superAdmin();
        $agency = $this->agency();
        $this->actingAs($agency, 'portal');

        $dtcm = $this->savedListing($agency, ['permit_type' => 'dtcm', 'listing_type' => 'rent']);
        $none = $this->savedListing($agency, ['permit_type' => 'none']);

        foreach ([$dtcm, $none] as $property) {
            $this->assertSame(Property::COMPLIANCE_PENDING, $property->compliance_status);
            $this->assertTrue($property->awaitingApproval());
            $this->assertSame('Awaiting approval', $property->complianceLabel());
            $this->assertFalse($property->canGoLive());
        }
        Notification::assertSentTo($admin, ListingReviewRequestedNotification::class);
        Mail::assertQueued(ListingReviewRequestedMail::class, fn ($m) => $m->hasTo('ops@example.test'));
        Notification::assertSentTo($agency, ListingComplianceNotification::class, fn ($n) => $n->event === 'submitted');

        // Super Admin approves: live, and the DTCM permit is recorded as checked by hand.
        Auth::guard('portal')->logout();
        $this->signIn($admin, 'cms');
        $this->get('/portal/listing-approvals?tab=pending')->assertOk()->assertSee('Awaiting approval');
        $this->get("/portal/listing-approvals/{$dtcm->id}")->assertOk()->assertSee('Approve &amp; publish', false);
        $this->post("/portal/listing-approvals/{$dtcm->id}/approve")->assertRedirect();

        $dtcm->refresh();
        $this->assertSame(Property::COMPLIANCE_APPROVED, $dtcm->compliance_status);
        $this->assertTrue($dtcm->status);
        $this->assertSame('manual', $dtcm->permit_verified_via);
        Notification::assertSentTo($agency, ListingComplianceNotification::class, fn ($n) => $n->event === 'approved');
        Mail::assertQueued(ListingComplianceMail::class, fn ($m) => $m->event === 'approved');
    }

    public function test_with_approval_on_even_a_verified_permit_waits_for_super_admin(): void
    {
        config(['permits.superadmin_approval' => true]);
        Notification::fake();
        $agency = $this->agency();
        $this->actingAs($agency, 'portal');

        $property = $this->savedListing($agency, $this->verifiedRera());
        $this->assertSame(Property::COMPLIANCE_PENDING, $property->compliance_status);
        $this->assertTrue($property->awaitingApproval());

        Auth::guard('portal')->logout();
        $this->signIn($this->superAdmin(), 'cms')->post("/portal/listing-approvals/{$property->id}/approve")->assertRedirect();
        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_APPROVED, $property->compliance_status);
        $this->assertTrue($property->status);
        $this->assertSame('dld', $property->permit_verified_via, 'an online verification is kept');
    }

    public function test_with_approval_on_only_permit_changes_need_a_new_approval(): void
    {
        config(['permits.superadmin_approval' => true]);
        Notification::fake();
        $agency = $this->agency();
        $this->actingAs($agency, 'portal');
        $compliance = app(ListingComplianceService::class);
        $property = $this->savedListing($agency, $this->verifiedRera());
        $property->update(['compliance_status' => Property::COMPLIANCE_APPROVED, 'status' => true]);

        // Description-only edit keeps the approval.
        $before = $compliance->snapshot($property);
        $property->update(['translations' => ['en' => ['title' => 'Listing', 'description' => 'New kitchen']]]);
        $compliance->afterSave($property, $before);
        $this->assertSame(Property::COMPLIANCE_APPROVED, $property->fresh()->compliance_status);
        $this->assertTrue($property->fresh()->status);

        // A new price goes back to Super Admin and comes off the website.
        $before = $compliance->snapshot($property->fresh());
        $property->update(['price' => 1250000]);
        $compliance->afterSave($property, $before);
        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_PENDING, $property->compliance_status);
        $this->assertFalse($property->status);
    }

    public function test_super_admin_saving_a_house_listing_approves_it(): void
    {
        config(['permits.superadmin_approval' => true]);
        $this->signIn($this->superAdmin(), 'cms');

        $property = $this->savedListing(null, ['permit_type' => 'dtcm', 'listing_type' => 'rent']);
        $this->assertSame(Property::COMPLIANCE_APPROVED, $property->compliance_status);
        $this->assertSame('manual', $property->permit_verified_via);
    }

    public function test_only_super_admin_approves_and_only_waiting_listings(): void
    {
        $agency = $this->agency();
        $this->actingAs($agency, 'portal');
        $waiting = $this->savedListing($agency, ['permit_type' => 'none']);
        $live = $this->savedListing($agency, $this->verifiedRera());

        $this->signIn($agency)->post("/portal/listing-approvals/{$waiting->id}/approve")->assertForbidden();
        $this->assertSame(Property::COMPLIANCE_PENDING, $waiting->fresh()->compliance_status);

        Auth::guard('portal')->logout();
        $this->signIn($this->superAdmin(), 'cms')->post("/portal/listing-approvals/{$live->id}/approve")->assertSessionHas('error');
    }

    public function test_take_down_keeps_the_listing_offline_until_its_permit_changes(): void
    {
        Mail::fake();
        $agency = $this->agency();
        $property = $this->property($agency);

        $this->signIn($this->superAdmin(), 'cms')->post("/portal/listing-approvals/{$property->id}/take-down")->assertRedirect();

        $property->refresh();
        $this->assertSame(Property::COMPLIANCE_CHANGES_REQUESTED, $property->compliance_status);
        $this->assertFalse($property->status);
        Mail::assertQueued(ListingComplianceMail::class, fn ($m) => $m->event === 'changes_requested' && $m->hasTo($agency->email));
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

        $this->signIn($agency)->post('/portal/properties', $this->propertyPayload([
            'permit_number' => '7112345678',
            'permit_qr' => UploadedFile::fake()->image('qr.png', 200, 200),
        ]))->assertSessionHasErrors('permit_number');
    }
}
