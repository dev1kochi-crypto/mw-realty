<?php

namespace App\Mail;

use App\Models\Plan;
use App\Models\PortalUser;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the Agent/Company the morning before their Stripe subscription auto-renews. */
class SubscriptionRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PortalUser $portalUser,
        public Plan $plan,
        public string $interval,
        public float $amount,
        public Carbon $renewsAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ' . $this->plan->getTranslation('name') . ' plan renews tomorrow — MW Realty');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.subscription-renewal-reminder',
            with: [
                'displayName' => $this->portalUser->displayName(),
                'planName' => $this->plan->getTranslation('name'),
                'interval' => $this->interval,
                'amount' => $this->amount,
                'renewsAt' => $this->renewsAt,
                'isPlanChange' => $this->portalUser->scheduled_plan_id === $this->plan->id,
                'plansUrl' => route('crm.app', 'plans'),
            ],
        );
    }
}
