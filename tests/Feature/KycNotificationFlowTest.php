<?php

namespace Tests\Feature;

use App\Mail\PortalAccountApproved;
use App\Mail\PortalAccountRegistered;
use App\Mail\PortalInfoRequestedMail;
use App\Models\CmsKit\SiteInformation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** KYC: submit → admin (every recipient) + account notified; info request; resubmit; approve. */
class KycNotificationFlowTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_every_step_emails_and_notifies_the_right_people(): void
    {
        Mail::fake();
        SiteInformation::create(['receipt_email' => 'sales@example.test, ops@example.test']);
        $agent = $this->independentAgent(['status' => 'pending', 'kyc_review_status' => 'draft']);
        $admin = $this->superAdmin();

        // Profile shows one KYC progress card (the layout's generic banner is hidden there).
        $this->crmApi($agent)->getJson('/api/crm/profile')->assertOk()
            ->assertJsonPath('kyc.title', 'Finish your KYC to unlock the CRM');
        $this->signIn($agent)->get('/portal/agency')->assertOk()->assertSee('portal-status-banner', false);

        // 1. The agent submits → both admin recipients emailed; the agent gets an in-app notice.
        $this->postJson('/api/crm/profile/submit')->assertOk();
        Mail::assertQueued(PortalAccountRegistered::class, fn ($m) => $m->hasTo('sales@example.test') && $m->hasTo('ops@example.test'));
        Mail::assertQueuedCount(1); // only the admin email — the agent gets no "we received it" email
        $this->assertSame(1, $agent->notifications()->where('type', \App\Notifications\PortalKycSubmittedNotification::class)->count());

        // Admin sidebar shows it as waiting.
        $this->signIn($admin, 'cms')->get(route('cms.portal-accounts.index', ['type' => 'agent']))->assertOk()->assertSee('KYC waiting for review');

        // 2. Admin asks for a missing document → the agent gets email + bell.
        $this->signIn($admin, 'cms')->post(route('cms.portal-accounts.request-info', $agent->id), ['request_items' => ['passport_document']])->assertRedirect();
        Mail::assertQueued(PortalInfoRequestedMail::class, fn ($m) => $m->hasTo($agent->email));
        $this->assertSame(1, $agent->notifications()->where('type', \App\Notifications\PortalInfoRequestedNotification::class)->count());

        // 3. The agent resubmits → admin again, the agent gets an in-app notice.
        $this->crmApi($agent->fresh())->postJson('/api/crm/profile/submit')->assertOk();
        Mail::assertQueued(PortalAccountRegistered::class, fn ($m) => $m->isResubmission);

        // 4. Approved → email + bell.
        $this->signIn($admin, 'cms')->postJson(route('cms.portal-accounts.update-status', $agent->id), ['status' => 'approved'])->assertOk();
        Mail::assertQueued(PortalAccountApproved::class, fn ($m) => $m->hasTo($agent->email));
        $this->assertSame(1, $agent->notifications()->where('type', \App\Notifications\PortalAccountApprovedNotification::class)->count());
    }

    public function test_recipient_email_accepts_a_comma_separated_list(): void
    {
        $this->assertSame(['a@x.test', 'b@x.test', 'c@x.test'], SiteInformation::splitEmails(' a@x.test, b@x.test;c@x.test ,A@x.test'));

        SiteInformation::create(['receipt_email' => 'a@x.test, b@x.test', 'email_1' => 'info@x.test']);
        $this->assertSame(['a@x.test', 'b@x.test'], SiteInformation::notificationEmail());

        SiteInformation::first()->update(['receipt_email' => null]);
        $this->assertSame(['info@x.test'], SiteInformation::notificationEmail(), 'falls back to Email 1');
    }
}
