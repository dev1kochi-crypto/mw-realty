<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the site admin when a new agent/company self-registers on the portal.
 */
class PortalAccountRegistered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser, public bool $isResubmission = false)
    {
    }

    public function envelope(): Envelope
    {
        $displayName = $this->portalUser->type === 'company'
            ? ($this->portalUser->company_name ?: $this->portalUser->name)
            : $this->portalUser->name;

        $subject = $this->isResubmission
            ? 'Resubmitted for Review — ' . $displayName
            : 'New ' . ucfirst($this->portalUser->type) . ' Registration — ' . $displayName;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $displayName = $this->portalUser->type === 'company'
            ? ($this->portalUser->company_name ?: $this->portalUser->name)
            : $this->portalUser->name;

        return new Content(
            view: 'emails.portal.registered',
            with: [
                'portalUser' => $this->portalUser,
                'displayName' => $displayName,
                'isResubmission' => $this->isResubmission,
                'reviewUrl' => route('cms.portal-accounts.show', ['id' => $this->portalUser->id, 'type' => $this->portalUser->type]),
            ],
        );
    }
}
