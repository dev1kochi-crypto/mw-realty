<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the agent/company when admin uses the free-text "Request Additional
 * Information" action (see PortalUserController::requestInfo()) — a general-purpose
 * ask that isn't tied to any one KYC document and doesn't change the account's status.
 */
class PortalInfoRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser, public string $message)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'MW Realty needs a bit more information from you');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.info-requested',
            with: [
                'portalUser' => $this->portalUser,
                'requestMessage' => $this->message,
                'profileUrl' => route('portal.profile.edit'),
            ],
        );
    }
}
