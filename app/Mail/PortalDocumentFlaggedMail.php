<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the agent/company when admin flags one of their specific KYC documents as
 * rejected (see PortalUserController::updateDocumentStatus()) — verified/pending
 * verdicts don't require the user to act, so only 'rejected' ever sends this.
 */
class PortalDocumentFlaggedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PortalUser $portalUser, public string $field, public ?string $note)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Action Needed: ' . PortalUser::documentLabel($this->field));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.document-flagged',
            with: [
                'portalUser' => $this->portalUser,
                'documentLabel' => PortalUser::documentLabel($this->field),
                'note' => $this->note,
                'profileUrl' => route('portal.profile.edit'),
            ],
        );
    }
}
