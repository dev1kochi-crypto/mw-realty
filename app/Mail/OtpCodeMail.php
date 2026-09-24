<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent on registration (and resend) with the plaintext code — see
 *  CustomerAuthController::register()/resendOtp() and Portal\PortalAuthController's equivalents,
 *  which only ever store the hashed version. Takes a plain name rather than a model so both the
 *  customer (User) and portal (PortalUser) registration flows can share this one Mailable. */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your MW Realty verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer.otp-code',
            with: [
                'name' => $this->name,
                'code' => $this->code,
            ],
        );
    }
}
