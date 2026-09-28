<?php

namespace Database\Seeders;

use App\Models\CmsKit\Admin;
use App\Models\PortalUser;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo portal Contact Us tickets covering every status, issue type and priority, with
 * back-dated conversations. Re-runnable: a ticket is matched on (portal user, subject).
 *
 *   php artisan db:seed --class=SupportTicketSeeder
 */
class SupportTicketSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::orderBy('id')->first();
        $clients = PortalUser::whereIn('type', ['agent', 'company'])
            ->where('email', '!=', 'admin@internal.mw-realty')
            ->orderBy('id')->get();

        if ($clients->isEmpty() || !$admin) {
            $this->command?->warn('Need at least one portal user and one CMS admin — skipped.');
            return;
        }

        foreach ($this->scenarios() as $i => $scenario) {
            $this->seedTicket($clients[$i % $clients->count()], $admin, $scenario);
        }

        $this->command?->info(SupportTicket::count() . ' support tickets in total.');
    }

    /**
     * Each step: [who, text, hours ago]. `who` is client | admin | a status key (a status change
     * by admin, or by the client for `resolved` when prefixed with "client:").
     */
    private function scenarios(): array
    {
        return [
            [
                'category' => 'kyc', 'priority' => 'high', 'subject' => 'KYC documents uploaded but still pending',
                'steps' => [
                    ['client', "I uploaded my Emirates ID and RERA card three days ago but my account still shows \"Pending approval\". Can you check?", 70],
                    ['in_progress', null, 52],
                    ['admin', "Thanks for your patience. Your Emirates ID is fine, but the RERA card image is blurry — could you upload a clearer scan from Profile > Documents?", 50],
                    ['awaiting_client', null, 50],
                ],
            ],
            [
                'category' => 'payments', 'priority' => 'normal', 'subject' => 'Invoice for September not received',
                'steps' => [
                    ['client', "My card was charged for the Premium plan on 1 September but I never got the invoice email.", 30],
                ],
            ],
            [
                'category' => 'listings', 'priority' => 'urgent', 'subject' => 'Listing photos not showing on the website',
                'steps' => [
                    ['client', "Photos for my Dubai Marina 2BR listing show on the portal but the public property page shows a blank gallery.", 6],
                    ['in_progress', null, 5],
                    ['admin', "We can reproduce this — the images were uploaded while our media service was down. Our team is re-processing them now.", 5],
                    ['client', "Thanks. It's a hot listing with viewings this weekend, so please prioritise.", 2],
                ],
            ],
            [
                'category' => 'plans', 'priority' => 'normal', 'subject' => 'How do I upgrade from Basic to Premium?',
                'steps' => [
                    ['client', "I want more listing slots. Is upgrading mid-month charged in full or pro-rated?", 200],
                    ['admin', "Upgrades are pro-rated — you only pay the difference for the remaining days. Go to Plans, choose Premium and the checkout shows the exact amount.", 190],
                    ['client', "Done, upgraded. Thank you!", 180],
                    ['client:resolved', null, 180],
                ],
            ],
            [
                'category' => 'leads', 'priority' => 'normal', 'subject' => 'Leads from the website not appearing in CRM',
                'steps' => [
                    ['client', "A buyer told me they submitted an enquiry on my Palm Jumeirah villa yesterday, but there's nothing in my CRM.", 100],
                    ['in_progress', null, 96],
                    ['admin', "The enquiry came in while the listing was briefly unassigned, so it landed in our unassigned queue. We've assigned it to you — you should see it in CRM now.", 95],
                    ['resolved', null, 95],
                ],
            ],
            [
                'category' => 'agency', 'priority' => 'normal', 'subject' => 'Agent invitation link expired',
                'steps' => [
                    ['client', "I invited a new agent to our agency but they say the set-password link has expired.", 400],
                    ['admin', "Setup links last 7 days. We've re-sent a fresh link to the agent's email.", 390],
                    ['resolved', null, 390],
                    ['closed', null, 200],
                ],
            ],
            [
                'category' => 'technical', 'priority' => 'high', 'subject' => 'Error when saving property with floor plan PDF',
                'steps' => [
                    ['client', "When I attach a floor plan PDF and click Save, I get \"Something went wrong\". Without the PDF it saves fine.", 20],
                    ['admin', "Could you tell us the file size? Uploads are limited to 20 MB.", 18],
                    ['awaiting_client', null, 18],
                    ['client', "It's 24 MB. I'll compress it — but a clearer error message would help.", 3],
                ],
            ],
            [
                'category' => 'feature', 'priority' => 'low', 'subject' => 'Request: bulk export of my listings to Excel',
                'steps' => [
                    ['client', "It would be great to export all my listings with prices and status to Excel for our weekly report.", 48],
                ],
            ],
            [
                'category' => 'account', 'priority' => 'normal', 'subject' => 'Change registered email address',
                'steps' => [
                    ['client', "I'm moving to a new company email. How do I change the email on my account?", 150],
                    ['admin', "Go to My Profile > Email and enter the new address — we'll send a verification code to it before switching.", 148],
                    ['awaiting_client', null, 148],
                ],
            ],
            [
                'category' => 'other', 'priority' => 'low', 'subject' => 'Office working hours during public holiday',
                'steps' => [
                    ['client', "Will your support team be available during the National Day holiday?", 600],
                    ['admin', "Yes — online support runs as usual; the office is closed on the holiday itself.", 590],
                    ['closed', null, 580],
                ],
            ],
            [
                'category' => 'payments', 'priority' => 'urgent', 'subject' => 'Charged twice for the same plan',
                'steps' => [
                    ['client', "I see two identical charges for my Gold plan on my bank statement this morning.", 1],
                ],
            ],
            [
                'category' => 'listings', 'priority' => 'normal', 'subject' => 'Featured listing ended earlier than expected',
                'steps' => [
                    ['client', "I featured my JVC apartment for 14 days but it stopped being featured after 10.", 80],
                    ['in_progress', null, 70],
                ],
            ],
        ];
    }

    private function seedTicket(PortalUser $client, Admin $admin, array $scenario): void
    {
        $ticket = SupportTicket::firstOrNew(['portal_user_id' => $client->id, 'subject' => $scenario['subject']]);
        if ($ticket->exists) {
            return;
        }

        $opened = now()->subHours($scenario['steps'][0][2]);
        $ticket->fill([
            'category' => $scenario['category'],
            'priority' => $scenario['priority'],
            'status' => SupportTicket::OPEN,
            'last_reply_by' => SupportTicketMessage::CLIENT,
            'last_reply_at' => $opened,
        ]);
        $ticket->created_at = $opened;
        $ticket->save();

        foreach ($scenario['steps'] as [$who, $text, $hoursAgo]) {
            $at = now()->subHours($hoursAgo);

            if ($who === 'client' || $who === 'admin') {
                $this->message($ticket, $who, $text, $at, $who === 'client' ? $client : null, $who === 'admin' ? $admin : null);
                $ticket->last_reply_by = $who;
                $ticket->last_reply_at = $at;
                // A client reply reopens a ticket that was waiting on them.
                if ($who === 'client' && $ticket->status === SupportTicket::AWAITING_CLIENT) {
                    $this->status($ticket, SupportTicket::OPEN, $client->displayName(), $at);
                }
                continue;
            }

            $byClient = str_starts_with($who, 'client:');
            $this->status($ticket, $byClient ? substr($who, 7) : $who, $byClient ? $client->displayName() : $admin->name, $at);
        }

        $ticket->updated_at = $ticket->last_reply_at;
        $ticket->save();
    }

    private function status(SupportTicket $ticket, string $status, string $actor, Carbon $at): void
    {
        $from = $ticket->statusLabel(true);
        $ticket->status = $status;
        $ticket->resolved_at = in_array($status, [SupportTicket::RESOLVED, SupportTicket::CLOSED], true) ? ($ticket->resolved_at ?? $at) : null;
        $ticket->closed_at = $status === SupportTicket::CLOSED ? $at : null;
        $this->message($ticket, SupportTicketMessage::SYSTEM, "Status changed from {$from} to {$ticket->statusLabel(true)} by {$actor}.", $at);
    }

    private function message(SupportTicket $ticket, string $type, string $body, Carbon $at, ?PortalUser $client = null, ?Admin $admin = null): void
    {
        $message = new SupportTicketMessage([
            'author_type' => $type,
            'portal_user_id' => $client?->id,
            'admin_id' => $admin?->id,
            'body' => $body,
        ]);
        $message->support_ticket_id = $ticket->id;
        $message->created_at = $message->updated_at = $at;
        $message->save();
    }
}
