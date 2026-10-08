<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To an agent after a lead file import: how many of its leads round robin gave them, in one
 * email instead of one per lead. $assigned: count, new, updated, sources [name => n].
 */
class LeadsImportAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PortalUser $agent,
        public array $assigned,
        public string $agencyName,
    ) {
    }

    public function envelope(): Envelope
    {
        $count = (int) ($this->assigned['count'] ?? 0);

        return new Envelope(subject: "{$count} imported lead" . ($count === 1 ? '' : 's') . ' assigned to you — MW Realty');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.leads-import-assigned',
            with: [
                'agent' => $this->agent,
                'agencyName' => $this->agencyName,
                'count' => (int) ($this->assigned['count'] ?? 0),
                'newCount' => (int) ($this->assigned['new'] ?? 0),
                'updatedCount' => (int) ($this->assigned['updated'] ?? 0),
                'sources' => collect($this->assigned['sources'] ?? [])->sortDesc()->all(),
                'leadsUrl' => route('portal.crm.leads.index'),
            ],
        );
    }
}
