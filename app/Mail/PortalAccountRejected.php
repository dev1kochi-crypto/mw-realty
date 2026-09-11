<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the agent/company when the admin rejects their account.
 */
class PortalAccountRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser, public ?string $reason = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on Your Account Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.rejected',
            with: [
                'portalUser' => $this->portalUser,
                'reason' => $this->reason,
            ],
        );
    }
}
