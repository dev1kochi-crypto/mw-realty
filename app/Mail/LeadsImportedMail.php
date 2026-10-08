<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the account that imported a lead file: the totals, plus the file itself with an
 * "Import Status" / "Import Remarks" column (LeadImportResultExport) — one email per import,
 * never one per lead. $summary: file, format, added, updated, skipped, agents [name => n].
 */
class LeadsImportedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public array $summary,
        public string $resultPath,
        public string $resultName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lead import finished: ' . $this->summary['added'] . ' added, ' . $this->summary['updated'] . ' updated, '
            . $this->summary['skipped'] . ' skipped — MW Realty');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.leads-imported',
            with: [
                'recipientName' => $this->recipientName,
                'summary' => $this->summary,
                'leadsUrl' => route('portal.crm.leads.index'),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->resultPath)->as($this->resultName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
