<?php

namespace App\Mail;

use App\Models\FacebookPageConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the agency / agent when Facebook stops accepting a connected Page's access (FacebookConnectionHealth). */
class FacebookPageReconnectMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FacebookPageConnection $pageConnection,
        public string $reason,
        public string $facebookMessage,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Reconnect your Facebook Page \"{$this->pageConnection->page_name}\" — MW Realty");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.facebook-reconnect',
            with: [
                'page' => $this->pageConnection,
                'owner' => $this->pageConnection->owner,
                'reason' => $this->reason,
                'facebookMessage' => $this->facebookMessage,
                'integrationsUrl' => route('crm.app', 'integrations/facebook'),
            ],
        );
    }
}
