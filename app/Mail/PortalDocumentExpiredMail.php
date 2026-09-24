<?php

namespace App\Mail;

use App\Models\PortalUser;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the agent/company themselves when one of their KYC/license documents' expiry date has passed. */
class PortalDocumentExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PortalUser $portalUser,
        public string $documentLabel,
        public ?Carbon $expiryDate,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->documentLabel} has expired — MW Realty");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.document-expired',
            with: [
                'portalUser' => $this->portalUser,
                'documentLabel' => $this->documentLabel,
                'expiryDate' => $this->expiryDate,
                'profileUrl' => route('portal.profile.edit'),
            ],
        );
    }
}
