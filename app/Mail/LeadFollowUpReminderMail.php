<?php

namespace App\Mail;

use App\Models\LeadFollowUp;
use App\Models\PortalUser;
use App\Services\Crm\LeadFollowUpService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to whoever is responsible for a lead when one of its follow-ups is due (LeadFollowUpService). */
class LeadFollowUpReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeadFollowUp $followUp, public PortalUser $recipient)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Follow-up due: ' . $this->followUp->typeLabel() . ' ' . ($this->followUp->lead->name ?: 'a lead') . ' — MW Realty');
    }

    public function content(): Content
    {
        $lead = $this->followUp->lead;

        return new Content(
            view: 'emails.portal.lead-follow-up-reminder',
            with: [
                'displayName' => $this->recipient->displayName(),
                'typeLabel' => $this->followUp->typeLabel(),
                'leadName' => $lead->name ?: 'Unknown',
                'leadPhone' => $lead->formatted_phone ?? $lead->phone,
                'leadEmail' => $lead->email,
                'dueLabel' => LeadFollowUpService::label($this->followUp->due_at),
                'note' => $this->followUp->note,
                'leadUrl' => route('crm.app', 'leads/' . $lead->id),
            ],
        );
    }
}
