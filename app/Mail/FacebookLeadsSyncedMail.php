<?php

namespace App\Mail;

use App\Models\FacebookPageConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One email after a bulk Facebook lead sync (Sync now / import at connect time / catch-up after a
 * reconnect) instead of one per lead — see FacebookLeadImporter::sync() / summary(). Live ad leads
 * (webhook) still email one by one through LeadCreationService.
 */
class FacebookLeadsSyncedMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CONTEXTS = [
        'sync' => 'Sync now',
        'import' => 'Import of existing leads',
        'catch-up' => 'Catch-up after reconnecting',
    ];

    public function __construct(
        public FacebookPageConnection $pageConnection,
        public array $summary,
        public string $context = 'sync',
    ) {
    }

    public function envelope(): Envelope
    {
        $count = $this->summary['new'] + $this->summary['merged'];

        return new Envelope(subject: "{$count} Facebook lead" . ($count === 1 ? '' : 's') . " synced from {$this->pageConnection->page_name} — MW Realty");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.facebook-leads-synced',
            with: [
                'page' => $this->pageConnection,
                'owner' => $this->pageConnection->owner,
                'summary' => $this->summary,
                'contextLabel' => self::CONTEXTS[$this->context] ?? 'Sync',
                'leadsUrl' => route('portal.crm.leads.index'),
            ],
        );
    }
}
