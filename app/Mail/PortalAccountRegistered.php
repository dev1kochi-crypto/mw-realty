<?php

namespace App\Mail;

use App\Models\PortalUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the site admin when an agent/company submits or resubmits KYC for review.
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
            ? 'KYC Resubmitted for Review — ' . $displayName
            : 'KYC Submitted for Approval — ' . $displayName;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $displayName = $this->portalUser->type === 'company'
            ? ($this->portalUser->company_name ?: $this->portalUser->name)
            : $this->portalUser->name;

        $requiredDocuments = $this->portalUser->type === 'agent'
            ? PortalUser::DOCUMENT_FIELDS
            : array_values(array_diff(PortalUser::DOCUMENT_FIELDS, ['rera_card_document']));

        $missingDocuments = collect($requiredDocuments)
            ->reject(fn ($field) => filled($this->portalUser->{$field}))
            ->map(fn ($field) => PortalUser::documentLabel($field))
            ->values();

        return new Content(
            view: 'emails.portal.registered',
            with: [
                'portalUser' => $this->portalUser,
                'displayName' => $displayName,
                'isResubmission' => $this->isResubmission,
                'reviewUrl' => route('cms.portal-accounts.show', ['id' => $this->portalUser->id, 'type' => $this->portalUser->type]),
                'documentsUploadedCount' => count($requiredDocuments) - $missingDocuments->count(),
                'documentsTotalCount' => count($requiredDocuments),
                'missingDocuments' => $missingDocuments,
            ],
        );
    }
}
