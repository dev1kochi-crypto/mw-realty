<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the agent/company when the admin approves their account.
 */
class PortalAccountApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Account Has Been Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.approved',
            with: [
                'portalUser' => $this->portalUser,
                'loginUrl' => route('portal.login'),
            ],
        );
    }
}
