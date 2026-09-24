<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the site admin when a still-pending agent/company uploads or replaces
 * one of their KYC documents — lets admin know there's something new to review
 * without waiting for the account's next full approval pass.
 */
class PortalKycUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser, public string $field)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'KYC Document Updated — ' . $this->portalUser->displayName());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.kyc-updated',
            with: [
                'portalUser' => $this->portalUser,
                'displayName' => $this->portalUser->displayName(),
                'documentLabel' => PortalUser::documentLabel($this->field),
                'reviewUrl' => route('cms.portal-accounts.show', ['id' => $this->portalUser->id, 'type' => $this->portalUser->type]),
            ],
        );
    }
}
