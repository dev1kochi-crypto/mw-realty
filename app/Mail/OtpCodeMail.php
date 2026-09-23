<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent on customer registration (and resend) with the plaintext code — see
 *  CustomerAuthController::register()/resendOtp(), which only ever stores the hashed version. */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
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
                'name' => $this->user->name,
                'code' => $this->code,
            ],
        );
    }
}
