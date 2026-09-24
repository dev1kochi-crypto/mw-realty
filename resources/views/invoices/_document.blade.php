{{-- Shared invoice document: rendered inside the PDF and on the web invoice pages. Table layout +
     inline-able CSS only, so dompdf renders it the same as browsers. --}}
<div class="inv">
    <table class="inv-head" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="inv-head__brand">
                @if($logo)
                <img src="{{ $logo }}" alt="{{ $company['name'] }}" class="inv-logo">
                @else
                <div class="inv-brand-name">{{ $company['name'] }}</div>
                @endif
            </td>
            <td class="inv-head__title" align="right">
                <div class="inv-title">INVOICE</div>
                <div class="inv-number">{{ $number }}</div>
            </td>
        </tr>
    </table>

    <table class="inv-meta" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="50%" valign="top">
                <div class="inv-label">From</div>
                <div class="inv-strong">{{ $company['name'] }}</div>
                @if($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if($company['po_box'])<div>P.O. Box {{ $company['po_box'] }}</div>@endif
                @if($company['phone'])<div>{{ $company['phone'] }}</div>@endif
                @if($company['email'])<div>{{ $company['email'] }}</div>@endif
            </td>
            <td width="50%" valign="top">
                <div class="inv-label">Billed to</div>
                @if($customer)
                <div class="inv-strong">{{ $customer->displayName() }}</div>
                <div>{{ ucfirst($customer->type) }} account</div>
                @if($customer->office_address)<div>{{ $customer->office_address }}</div>@endif
                @if($customer->trn_number)<div>TRN: {{ $customer->trn_number }}</div>@endif
                <div>{{ $customer->email }}</div>
                @if($customer->phone)<div>{{ $customer->phone }}</div>@endif
                @else
                <div class="inv-strong">Deleted account</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="inv-facts" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td><div class="inv-label">Invoice date</div><div class="inv-strong">{{ $issued->format('d M Y') }}</div></td>
            <td><div class="inv-label">Payment method</div><div class="inv-strong">{{ $method }}</div></td>
            <td><div class="inv-label">Billing</div><div class="inv-strong">{{ ucfirst(str_replace('_', ' ', $payment->billing_cycle)) }}</div></td>
            <td align="right">
                <div class="inv-label">Status</div>
                <span class="inv-status {{ $isPaid ? 'inv-status--paid' : 'inv-status--failed' }}">{{ $isPaid ? 'PAID' : 'PAYMENT FAILED' }}</span>
            </td>
        </tr>
    </table>

    <table class="inv-lines" width="100%" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th align="left">Description</th>
                <th align="right" width="22%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="inv-strong">{{ $line['description'] }}</div>
                    @if($line['period'])<div class="inv-muted">Service period: {{ $line['period'] }}</div>@endif
                </td>
                <td align="right">AED {{ number_format($line['amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="inv-totals" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="55%"></td>
            <td>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr><td>Subtotal</td><td align="right">AED {{ number_format($line['amount'], 2) }}</td></tr>
                    @if($discount > 0)
                    <tr class="inv-discount"><td>Discount{{ $payment->coupon_code ? ' (' . $payment->coupon_code . ')' : '' }}</td><td align="right">&minus;AED {{ number_format($discount, 2) }}</td></tr>
                    @endif
                    <tr class="inv-grand"><td>{{ $isPaid ? 'Total paid' : 'Amount due' }}</td><td align="right">AED {{ number_format($total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!$isPaid && $payment->failure_reason)
    <div class="inv-note inv-note--warn">{{ $payment->failure_reason }}</div>
    @endif

    <div class="inv-foot">
        Thank you for choosing {{ $company['name'] }}. This invoice was generated electronically and is valid without a signature.
        @if($payment->stripe_invoice_id)<br>Payment reference: {{ $payment->stripe_invoice_id }}@endif
    </div>
</div>
