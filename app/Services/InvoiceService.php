<?php

namespace App\Services;

use App\Mail\PlanInvoiceMail;
use App\Models\CmsKit\SiteInformation;
use App\Models\PlanPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Plan payment invoices on our own domain: the same document is rendered as the web invoice
 * page (admin + portal), the downloadable PDF, and the PDF attached to the invoice email.
 */
class InvoiceService
{
    public static function number(PlanPayment $payment): string
    {
        return 'INV-' . ($payment->paid_at ?? $payment->created_at ?? now())->format('Y') . '-' . str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
    }

    public static function filename(PlanPayment $payment): string
    {
        return self::number($payment) . '.pdf';
    }

    /** Everything the invoice document needs. */
    public function data(PlanPayment $payment): array
    {
        $payment->loadMissing(['portalUser', 'plan']);
        $site = SiteInformation::first();
        $issued = $payment->paid_at ?? $payment->created_at ?? now();
        $yearly = $payment->billing_cycle === 'yearly';
        $periodEnd = Carbon::parse($issued)->add($yearly ? '1 year' : '1 month')->subDay();
        $original = (float) ($payment->original_amount ?? $payment->amount);

        return [
            'payment' => $payment,
            'number' => self::number($payment),
            'issued' => $issued,
            'customer' => $payment->portalUser,
            'company' => [
                'name' => $site->company_name ?? config('app.name', 'MW Realty'),
                'address' => $site->address ?? null,
                'phone' => $site->phone_1 ?? null,
                'email' => $site->email_1 ?? null,
                'po_box' => $site->po_box ?? null,
            ],
            'logo' => $this->logoDataUri($site?->logo),
            'line' => [
                'description' => $payment->plan_name . ' Plan — ' . ($yearly ? 'Yearly' : ($payment->billing_cycle === 'one_time' ? 'One-time' : 'Monthly')) . ' subscription',
                'period' => $payment->billing_cycle === 'one_time' ? null : Carbon::parse($issued)->format('d M Y') . ' – ' . $periodEnd->format('d M Y'),
                'amount' => $original,
            ],
            'discount' => (float) ($payment->discount_amount ?? 0),
            'total' => (float) $payment->amount,
            'method' => $payment->stripe_invoice_id ? 'Card (via Stripe)' : 'Recorded by MW Realty',
            'isPaid' => $payment->isPaid(),
        ];
    }

    /** Logo embedded as a data URI so it renders in the PDF (no remote fetch) and on the page. */
    private function logoDataUri(?string $logo): ?string
    {
        // Cloudinary logo: fetch a 400px PNG rendition (keeps invoices small) and cache it a day.
        if (CloudinaryMedia::isCloudinaryUrl($logo)) {
            $uri = \Illuminate\Support\Facades\Cache::remember('invoice-logo:' . md5($logo), 86400, function () use ($logo) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(10)->get(str_replace('/upload/', '/upload/w_400,f_png/', $logo));
                    return $response->successful() ? 'data:image/png;base64,' . base64_encode($response->body()) : null;
                } catch (\Throwable) {
                    return null;
                }
            });
            if ($uri) {
                return $uri;
            }
            $logo = null; // fall back to the bundled logo
        }

        $candidates = array_filter([
            $logo ? storage_path('app/public/' . $logo) : null,
            public_path('frontend/assets/images/logo.png'),
        ]);
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        return null;
    }

    public function pdf(PlanPayment $payment)
    {
        return Pdf::loadView('invoices.pdf', $this->data($payment))->setPaper('a4');
    }

    /** Emails the invoice (PDF attached) once per paid payment. Never throws — a mail failure mustn't break billing. */
    public function sendOnce(PlanPayment $payment): void
    {
        $payment->refresh();
        if (!$payment->isPaid() || $payment->invoice_sent_at || !$payment->portalUser?->email) {
            return;
        }

        try {
            Mail::to($payment->portalUser->email)->send(new PlanInvoiceMail($payment));
            $payment->forceFill(['invoice_sent_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Plan invoice email failed for payment #' . $payment->id . ': ' . $e->getMessage());
        }
    }
}
