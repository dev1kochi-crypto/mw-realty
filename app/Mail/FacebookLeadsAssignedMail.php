<?php

namespace App\Mail;

use App\Models\FacebookPageConnection;
use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To an agent after a bulk Facebook lead sync: the leads round robin gave them, in one email instead
 * of one per lead (FacebookLeadImporter::emailSummary). $leads lists the first ones; $count is all.
 */
class FacebookLeadsAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FacebookPageConnection $pageConnection,
        public PortalUser $agent,
        public int $count,
        public array $leads,
        public string $context = 'sync',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->count} Facebook lead" . ($this->count === 1 ? '' : 's') . " assigned to you — MW Realty");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.facebook-leads-assigned',
            with: [
                'page' => $this->pageConnection,
                'agent' => $this->agent,
                'count' => $this->count,
                'leads' => $this->leads,
                'contextLabel' => FacebookLeadsSyncedMail::CONTEXTS[$this->context] ?? 'Sync',
                'leadsUrl' => route('portal.crm.leads.index'),
            ],
        );
    }
}
