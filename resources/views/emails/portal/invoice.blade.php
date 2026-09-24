@extends('emails.layout')

@section('subject', 'Payment received — Invoice ' . $number)

@section('content')
<p style="margin:0 0 16px;">Hi {{ $displayName }},</p>

<p style="margin:0 0 20px;">Thank you — we've received your payment. Your invoice is attached as a PDF, and the details are below.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eceff5; border-radius:12px; border-collapse:separate; overflow:hidden;">
    <tr>
        <td style="padding:18px 20px; background-color:#f7f8fc; border-bottom:1px solid #eceff5;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-size:12px; color:#838aa3; text-transform:uppercase; letter-spacing:0.05em; font-weight:bold;">Amount paid</td>
                    <td align="right" style="font-size:12px; color:#838aa3; text-transform:uppercase; letter-spacing:0.05em; font-weight:bold;">Invoice</td>
                </tr>
                <tr>
                    <td style="font-size:26px; font-weight:bold; color:#1c2340; padding-top:4px;">AED {{ number_format($total, 2) }}</td>
                    <td align="right" style="font-size:14px; font-weight:bold; color:#264373; padding-top:4px;">{{ $number }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                <tr>
                    <td style="padding:6px 0; color:#1c2340;">
                        <strong>{{ $line['description'] }}</strong>
                        @if($line['period'])<div style="font-size:12px; color:#838aa3;">{{ $line['period'] }}</div>@endif
                    </td>
                    <td align="right" style="padding:6px 0; color:#1c2340; white-space:nowrap;">AED {{ number_format($line['amount'], 2) }}</td>
                </tr>
                @if($discount > 0)
                <tr>
                    <td style="padding:6px 0; color:#0f9d58;">Discount{{ $payment->coupon_code ? ' (' . $payment->coupon_code . ')' : '' }}</td>
                    <td align="right" style="padding:6px 0; color:#0f9d58; white-space:nowrap;">−AED {{ number_format($discount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 0 4px; border-top:1px solid #eceff5; font-weight:bold; color:#1c2340;">Total paid</td>
                    <td align="right" style="padding:12px 0 4px; border-top:1px solid #eceff5; font-weight:bold; color:#1c2340; white-space:nowrap;">AED {{ number_format($total, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:14px 20px; background-color:#f7f8fc; border-top:1px solid #eceff5; font-size:12px; color:#5b6478;">
            Paid on <strong>{{ $issued->format('d M Y') }}</strong> &nbsp;·&nbsp; {{ $method }}
        </td>
    </tr>
</table>

<p style="margin:20px 0 0; font-size:13px; color:#5b6478;">You can view and download all your invoices anytime from Plans &rarr; Payment History in your portal.</p>
@endsection

@section('cta_url', $invoiceUrl)
@section('cta_label', 'View Invoice')
