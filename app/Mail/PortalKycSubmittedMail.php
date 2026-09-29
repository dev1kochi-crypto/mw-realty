<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the agent/company themselves when they submit (or resubmit, after admin asked for
 * changes) their KYC — confirms it reached our team. Admin gets PortalAccountRegistered.
 */
class PortalKycSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser, public bool $isResubmission = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isResubmission ? 'We received your updated KYC details' : 'We received your KYC details');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.kyc-submitted',
            with: [
                'portalUser' => $this->portalUser,
                'isResubmission' => $this->isResubmission,
                'profileUrl' => route('portal.profile.edit'),
            ],
        );
    }
}
