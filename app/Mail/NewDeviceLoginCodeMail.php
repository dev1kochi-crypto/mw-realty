<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent when a portal login comes from a browser the account hasn't verified before — see
 *  PortalAuthController::login(). Only the hashed code is kept (in the session). */
class NewDeviceLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $code,
        public string $device,
        public ?string $ip,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New sign-in to your MW Realty account — verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.new-device-login-code',
            with: [
                'name' => $this->name,
                'code' => $this->code,
                'device' => $this->device,
                'ip' => $this->ip,
            ],
        );
    }
}
