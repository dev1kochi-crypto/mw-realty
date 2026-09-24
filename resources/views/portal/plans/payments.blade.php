@extends('portal.layouts.app')

@section('title', 'Payment History')

@push('styles')
<style>
    .ph-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; }
    .ph-head__title { font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--portal-text); }
    .ph-head__sub { color: var(--portal-muted); margin: 0.2rem 0 0; font-size: 0.9rem; }

    .ph-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .ph-stat { display: flex; align-items: center; gap: 0.9rem; padding: 1.1rem 1.25rem; border-radius: 16px; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: var(--portal-shadow); }
    .ph-stat--hero { background: linear-gradient(135deg, var(--portal-primary-dark), var(--portal-primary) 60%, var(--portal-accent)); color: #fff; border: 0; }
    .ph-stat__icon { width: 2.8rem; height: 2.8rem; flex-shrink: 0; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; background: rgba(36, 67, 115, 0.08); color: var(--portal-primary); }
    .ph-stat--hero .ph-stat__icon { background: rgba(255, 255, 255, 0.16); color: #fde68a; }
    .ph-stat__value { font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
    .ph-stat__label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--portal-muted); }
    .ph-stat--hero .ph-stat__label { color: rgba(255, 255, 255, 0.75); }

    .ph-month { margin-bottom: 1.5rem; }
    .ph-month__label { display: flex; align-items: center; gap: 0.75rem; font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--portal-muted); margin-bottom: 0.75rem; }
    .ph-month__label::after { content: ''; flex: 1; height: 1px; background: var(--portal-border); }

    .ph-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.25rem; border-radius: 14px; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: 0 4px 14px rgba(36, 67, 115, 0.05); margin-bottom: 0.6rem; transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .ph-row:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(36, 67, 115, 0.1); }
    .ph-row__date { width: 3.4rem; flex-shrink: 0; text-align: center; border-radius: 12px; background: var(--portal-bg); padding: 0.4rem 0; }
    .ph-row__day { font-size: 1.25rem; font-weight: 800; line-height: 1; color: var(--portal-text); }
    .ph-row__mon { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--portal-muted); }
    .ph-row__main { flex: 1; min-width: 180px; }
    .ph-row__plan { font-weight: 700; color: var(--portal-text); }
    .ph-row__meta { font-size: 0.8rem; color: var(--portal-muted); }
    .ph-coupon { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.55rem; border-radius: 50px; background: rgba(22, 163, 74, 0.1); color: #16a34a; font-size: 0.72rem; font-weight: 700; }
    .ph-row__amount { text-align: right; min-width: 120px; }
    .ph-row__amount strong { font-size: 1.1rem; font-weight: 800; color: var(--portal-text); }
    .ph-row__amount s { display: block; font-size: 0.78rem; color: var(--portal-muted); }
    .ph-paid { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 50px; background: rgba(22, 163, 74, 0.12); color: #16a34a; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
    .ph-row__action { min-width: 150px; text-align: right; }
    .ph-paid--failed { background: rgba(220, 38, 38, 0.1); color: #dc2626; }

    .ph-empty { text-align: center; padding: 3.5rem 1rem; border-radius: 18px; background: var(--portal-surface); border: 1px dashed var(--portal-border); color: var(--portal-muted); }
    .ph-empty i { font-size: 2.4rem; opacity: 0.35; margin-bottom: 0.75rem; }

    @media (max-width: 575.98px) {
        .ph-row__amount, .ph-row__action { text-align: left; width: auto; min-width: 0; }
    }
</style>
@endpush

@section('content')
<div class="ph-head">
    <div>
        <h1 class="ph-head__title">Payment History</h1>
        <p class="ph-head__sub">Your plan payments from the last 12 months ({{ $since->format('d M Y') }} &ndash; today).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('portal.plans.index') }}" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Plans</a>
        @if($stripeEnabled && $owner->stripe_customer_id)
        <form action="{{ route('portal.plans.billing-portal') }}" method="POST">@csrf
            <button class="btn btn-portal-primary btn-sm"><i class="fas fa-file-invoice me-1"></i>Card &amp; all invoices</button>
        </form>
        @endif
    </div>
</div>

<div class="ph-stats">
    <div class="ph-stat ph-stat--hero">
        <span class="ph-stat__icon"><i class="fas fa-wallet"></i></span>
        <div><div class="ph-stat__value">AED {{ number_format($summary['total'], 2) }}</div><div class="ph-stat__label">Paid in 12 months</div></div>
    </div>
    <div class="ph-stat">
        <span class="ph-stat__icon"><i class="fas fa-receipt"></i></span>
        <div><div class="ph-stat__value">{{ $summary['count'] }}</div><div class="ph-stat__label">Payments</div></div>
    </div>
    <div class="ph-stat">
        <span class="ph-stat__icon" style="background: rgba(22,163,74,0.1); color: #16a34a;"><i class="fas fa-tags"></i></span>
        <div><div class="ph-stat__value">AED {{ number_format($summary['saved'], 2) }}</div><div class="ph-stat__label">Saved with coupons</div></div>
    </div>
    <div class="ph-stat">
        <span class="ph-stat__icon"><i class="fas fa-calendar-check"></i></span>
        <div><div class="ph-stat__value">{{ $summary['last']?->paid_at->format('d M Y') ?? '—' }}</div><div class="ph-stat__label">Last payment</div></div>
    </div>
</div>

@forelse($months as $month => $rows)
<div class="ph-month">
    <div class="ph-month__label">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</div>
    @foreach($rows as $payment)
    <div class="ph-row">
        <div class="ph-row__date">
            <div class="ph-row__day">{{ $payment->paid_at->format('d') }}</div>
            <div class="ph-row__mon">{{ $payment->paid_at->format('M') }}</div>
        </div>
        <div class="ph-row__main">
            <div class="ph-row__plan">{{ $payment->plan_name }} plan</div>
            <div class="ph-row__meta d-flex align-items-center gap-2 flex-wrap">
                <span>{{ ucfirst(str_replace('_', ' ', $payment->billing_cycle)) }}</span>
                <span>&middot;</span>
                <span>{{ $payment->stripe_invoice_id ? 'Card payment' : 'Recorded by MW Realty' }}</span>
                @if($payment->coupon_code)
                <span class="ph-coupon"><i class="fas fa-ticket-alt"></i>{{ $payment->coupon_code }} &minus;AED {{ number_format($payment->discount_amount, 2) }}</span>
                @endif
            </div>
        </div>
        <div class="ph-row__amount">
            <strong>AED {{ number_format($payment->amount, 2) }}</strong>
            @if($payment->original_amount)<s>AED {{ number_format($payment->original_amount, 2) }}</s>@endif
        </div>
        @if($payment->isPaid())
        <span class="ph-paid"><i class="fas fa-check-circle"></i>Paid</span>
        @else
        <span class="ph-paid ph-paid--failed" title="{{ $payment->failure_reason }}"><i class="fas fa-exclamation-circle"></i>Failed</span>
        @endif
        <div class="ph-row__action d-flex gap-1 justify-content-end">
            <a href="{{ route('portal.plans.payments.show', $payment->id) }}" class="btn btn-portal-light btn-sm"><i class="fas fa-file-invoice me-1"></i>Invoice</a>
            <a href="{{ route('portal.plans.payments.pdf', $payment->id) }}" class="btn btn-portal-light btn-sm" title="Download PDF"><i class="fas fa-file-pdf text-danger"></i></a>
        </div>
    </div>
    @endforeach
</div>
@empty
<div class="ph-empty">
    <i class="fas fa-receipt d-block"></i>
    <div class="fw-bold mb-1">No payments in the last 12 months</div>
    <div class="small">Payments for paid plans will appear here. <a href="{{ route('portal.plans.index') }}">View plans</a></div>
</div>
@endforelse
@endsection
