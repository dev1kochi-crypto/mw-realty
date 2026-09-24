<?php

namespace App\Mail;

use App\Models\PlanPayment;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the Agent/Company after a plan payment is taken, with the PDF invoice attached. */
class PlanInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PlanPayment $payment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment received — Invoice ' . InvoiceService::number($this->payment));
    }

    public function content(): Content
    {
        $data = app(InvoiceService::class)->data($this->payment);

        return new Content(
            view: 'emails.portal.invoice',
            with: $data + [
                'displayName' => $this->payment->portalUser?->displayName(),
                'invoiceUrl' => route('portal.plans.payments.show', $this->payment->id),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(InvoiceService::class)->pdf($this->payment)->output(), InvoiceService::filename($this->payment))
                ->withMime('application/pdf'),
        ];
    }
}
