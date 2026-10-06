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
 * To an agent after a bulk Facebook lead sync: a summary of the leads round robin gave them, in one
 * email instead of one per lead (FacebookLeadImporter::emailSummary). $assigned holds the totals:
 * count, new, updated, sources [name => n] — no individual leads; they open them in Leads.
 */
class FacebookLeadsAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FacebookPageConnection $pageConnection,
        public PortalUser $agent,
        public array $assigned,
        public string $context = 'sync',
    ) {
    }

    public function envelope(): Envelope
    {
        $count = (int) ($this->assigned['count'] ?? 0);

        return new Envelope(subject: "{$count} Facebook lead" . ($count === 1 ? '' : 's') . " assigned to you — MW Realty");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.facebook-leads-assigned',
            with: [
                'page' => $this->pageConnection,
                'agent' => $this->agent,
                'count' => (int) ($this->assigned['count'] ?? 0),
                'newCount' => (int) ($this->assigned['new'] ?? 0),
                'updatedCount' => (int) ($this->assigned['updated'] ?? 0),
                'sources' => collect($this->assigned['sources'] ?? [])->sortDesc()->all(),
                'contextLabel' => FacebookLeadsSyncedMail::CONTEXTS[$this->context] ?? 'Sync',
                'leadsUrl' => route('portal.crm.leads.index'),
            ],
        );
    }
}
