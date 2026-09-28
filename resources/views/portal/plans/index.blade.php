@extends('portal.layouts.app')

@section('title', 'Plans')

@php
    $currentPlan = $owner->plan;
    $used = $owner->properties()->count();
    $limit = $currentPlan && !$currentPlan->isUnlimited() ? (int) $currentPlan->property_limit : null;
    $usagePct = $limit ? min(100, round($used / max(1, $limit) * 100)) : 100;
    $tierIcons = ['fa-seedling', 'fa-paper-plane', 'fa-rocket', 'fa-crown', 'fa-gem'];
@endphp

@push('styles')
<style>
    /* Compact summary bar: plan, usage, subscription status and billing actions in one row */
    .pl-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 0.8rem 1rem; border-radius: 14px; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: var(--portal-shadow); }
    .pl-bar__main { display: flex; align-items: center; gap: 0.8rem; min-width: 0; flex: 1 1 420px; }
    .pl-bar__icon { width: 2.4rem; height: 2.4rem; flex-shrink: 0; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); }
    .pl-bar__title { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; font-weight: 800; color: var(--portal-text); }
    .pl-bar__meta { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; font-size: 0.8rem; color: var(--portal-muted); margin-top: 0.15rem; }
    .pl-bar__meta strong { color: var(--portal-text); font-weight: 700; }
    .pl-bar__usage { display: inline-flex; align-items: center; gap: 0.45rem; }
    .pl-bar__meter { width: 64px; height: 6px; border-radius: 50px; background: var(--portal-bg); overflow: hidden; }
    .pl-bar__meter span { display: block; height: 100%; border-radius: 50px; background: linear-gradient(90deg, #fbbf24, #f59e0b); }
    .pl-bar__meter span.is-full { background: linear-gradient(90deg, #fca5a5, #ef4444); }
    .pl-bar__sep { color: #c3c9dc; }
    .pl-bar__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    /* Billing period toggle + coupon, one row above the plans */
    .pl-controls { display: flex; align-items: center; justify-content: center; gap: 0.75rem 1.25rem; flex-wrap: wrap; margin: 0 0 1.5rem; }
    .pl-coupon { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; padding: 0.3rem 0.35rem 0.3rem 0.3rem; border-radius: 50px; background: var(--portal-surface); border: 1px dashed rgba(36, 67, 115, 0.35); }
    .pl-coupon__form { display: flex; align-items: center; gap: 0.4rem; }
    .pl-coupon__icon { width: 2rem; height: 2rem; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; background: linear-gradient(135deg, var(--portal-accent), #f97316); color: #fff; }
    .pl-coupon__form .form-control { width: 150px; height: 2rem; padding: 0 0.6rem; font-size: 0.82rem; font-weight: 700; letter-spacing: 0.06em; border: 0; box-shadow: none; background: transparent; }
    .pl-coupon__form .btn { border-radius: 50px; }
    .pl-coupon__status { font-size: 0.75rem; color: var(--portal-muted); padding-right: 0.6rem; max-width: 260px; }
    .pl-coupon.is-applied { border-style: solid; border-color: #16a34a; background: #f0fdf4; }
    @media (max-width: 575.98px) {
        .pl-coupon { border-radius: 14px; width: 100%; }
        .pl-coupon__form { width: 100%; }
        .pl-coupon__form .form-control { flex: 1; width: auto; }
        .pl-coupon__status { max-width: none; padding: 0 0.4rem 0.2rem; }
        .pl-bar__actions { width: 100%; }
    }

    .pl-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; align-items: stretch; padding-top: 0.75rem; }
    .pl-card { position: relative; display: flex; flex-direction: column; background: var(--portal-surface); border: 1px solid var(--portal-border); border-radius: 20px; padding: 1.75rem 1.5rem 1.5rem; box-shadow: var(--portal-shadow); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .pl-card:hover { transform: translateY(-6px); box-shadow: 0 22px 44px rgba(36, 67, 115, 0.16); }
    .pl-card--popular { background: linear-gradient(160deg, var(--portal-primary-dark), var(--portal-primary)); color: #fff; border: 0; box-shadow: 0 24px 50px rgba(36, 67, 115, 0.35); }
    .pl-card--current { border: 2px solid #16a34a; }
    .pl-ribbon { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); white-space: nowrap; padding: 0.35rem 1rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #fff; background: linear-gradient(135deg, var(--portal-accent), #f97316); box-shadow: 0 8px 18px rgba(202, 40, 68, 0.35); }
    .pl-ribbon--current { background: linear-gradient(135deg, #16a34a, #22c55e); box-shadow: 0 8px 18px rgba(22, 163, 74, 0.3); }

    .pl-icon { width: 3rem; height: 3rem; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: rgba(36, 67, 115, 0.08); color: var(--portal-primary); margin-bottom: 1rem; }
    .pl-card--popular .pl-icon { background: rgba(255, 255, 255, 0.15); color: #fde68a; }
    .pl-name { font-size: 1.25rem; font-weight: 800; margin: 0; }
    .pl-desc { font-size: 0.85rem; color: var(--portal-muted); margin: 0.25rem 0 1.25rem; min-height: 1.3em; }
    .pl-card--popular .pl-desc { color: rgba(255, 255, 255, 0.75); }

    .pl-price { display: flex; align-items: baseline; gap: 0.35rem; padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px dashed var(--portal-border); }
    .pl-card--popular .pl-price { border-color: rgba(255, 255, 255, 0.2); }
    .pl-price__currency { font-size: 0.9rem; font-weight: 700; color: var(--portal-muted); align-self: flex-start; margin-top: 0.45rem; }
    .pl-price__amount { font-size: 2.6rem; font-weight: 800; line-height: 1; letter-spacing: -0.02em; }
    .pl-price__cycle { font-size: 0.85rem; color: var(--portal-muted); font-weight: 600; }
    .pl-card--popular .pl-price__currency, .pl-card--popular .pl-price__cycle { color: rgba(255, 255, 255, 0.7); }

    .pl-features { list-style: none; padding: 0; margin: 0 0 1.5rem; flex-grow: 1; display: flex; flex-direction: column; gap: 0.7rem; font-size: 0.9rem; }
    .pl-features li { display: flex; gap: 0.6rem; align-items: flex-start; }
    .pl-check { flex-shrink: 0; width: 1.3rem; height: 1.3rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.62rem; background: rgba(22, 163, 74, 0.12); color: #16a34a; margin-top: 0.05rem; }
    .pl-card--popular .pl-check { background: rgba(253, 230, 138, 0.2); color: #fde68a; }
    .pl-features li.is-highlight { font-weight: 700; }
    .pl-features li.is-excluded { color: var(--portal-muted); text-decoration: line-through; text-decoration-color: rgba(107, 112, 148, 0.4); }
    .pl-features li.is-excluded .pl-check { background: rgba(107, 112, 148, 0.1); color: var(--portal-muted); }
    .pl-card--popular .pl-features li.is-excluded { color: rgba(255, 255, 255, 0.5); }
    .pl-card--popular .pl-features li.is-excluded .pl-check { background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.5); }

    .pl-btn { width: 100%; padding: 0.8rem 1rem; border-radius: 12px; font-weight: 700; border: 0; transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .pl-btn:hover:not(:disabled) { transform: translateY(-2px); }
    .pl-btn--primary { background: linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); color: #fff; box-shadow: 0 12px 24px rgba(36, 67, 115, 0.28); }
    .pl-btn--light { background: #fff; color: var(--portal-primary-dark); box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18); }
    .pl-btn--muted { background: var(--portal-bg); color: var(--portal-muted); cursor: default; }
    .pl-card--popular .pl-btn--muted { background: rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.8); }
    .pl-btn--current { background: rgba(22, 163, 74, 0.1); color: #16a34a; cursor: default; }

    .pl-sub__badge { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.2rem 0.6rem; border-radius: 50px; }
    .pl-sub__badge--ok { background: rgba(22, 163, 74, 0.12); color: #16a34a; }
    .pl-sub__badge--warn { background: rgba(245, 158, 11, 0.15); color: #b45309; }
    .pl-discount { margin: -0.75rem 0 1.25rem; font-size: 0.82rem; font-weight: 700; color: #16a34a; }
    .pl-discount.is-invalid { color: var(--portal-muted); font-weight: 600; }
    .pl-card--popular .pl-discount { color: #86efac; }
    .pl-card--popular .pl-discount.is-invalid { color: rgba(255, 255, 255, 0.6); }
    .pl-price__old { font-size: 1rem; font-weight: 700; color: var(--portal-muted); text-decoration: line-through; margin-left: 0.25rem; }
    .pl-card--popular .pl-price__old { color: rgba(255, 255, 255, 0.55); }

    .pl-note { text-align: center; color: var(--portal-muted); font-size: 0.85rem; margin-top: 2rem; }

    @media (min-width: 1200px) { .pl-card--popular { transform: scale(1.03); } .pl-card--popular:hover { transform: scale(1.03) translateY(-6px); } }
</style>
@endpush

@push('styles')
<style>
    .pl-toggle { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem; border-radius: 50px; background: var(--portal-surface); border: 1px solid var(--portal-border); box-shadow: var(--portal-shadow); }
    .pl-toggle button { border: 0; background: transparent; padding: 0.55rem 1.4rem; border-radius: 50px; font-weight: 700; font-size: 0.9rem; color: var(--portal-muted); transition: all 0.2s ease; }
    .pl-toggle button.is-active { background: linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); color: #fff; box-shadow: 0 8px 18px rgba(36, 67, 115, 0.3); }
    .pl-toggle__save { margin-left: 0.4rem; padding: 0.1rem 0.5rem; border-radius: 50px; font-size: 0.68rem; font-weight: 800; background: #dcfce7; color: #15803d; }
    .pl-toggle button.is-active .pl-toggle__save { background: rgba(255, 255, 255, 0.2); color: #fff; }
    .pl-yearly-note { margin: -0.9rem 0 1.1rem; font-size: 0.8rem; font-weight: 600; color: #16a34a; }
    .pl-card--popular .pl-yearly-note { color: #86efac; }
    .pl-yearly-note.is-muted { color: var(--portal-muted); }
    .pl-card--popular .pl-yearly-note.is-muted { color: rgba(255, 255, 255, 0.6); }
    [data-interval-block][hidden] { display: none !important; }

    /* Plan-change modal */
    .pl-modal .modal-dialog { max-width: 480px; }
    .pl-modal .modal-content { border: 0; border-radius: 24px; overflow: hidden; box-shadow: 0 30px 80px rgba(20, 30, 60, 0.35); }
    .pc-top { position: relative; padding: 1.5rem 1.5rem 1.25rem; text-align: center; }
    .pc-top .btn-close { position: absolute; top: 1.1rem; right: 1.1rem; }
    .pc-kind { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
    .pc-kind--upgrade { background: #dcfce7; color: #15803d; }
    .pc-kind--downgrade { background: #fef3c7; color: #b45309; }
    .pc-kind--cancel { background: #fee2e2; color: #b91c1c; }
    .pc-title { font-size: 1.3rem; font-weight: 800; color: var(--portal-text); margin: 0.6rem 0 0; }

    .pc-flow { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 0.6rem; padding: 0 1.5rem; }
    .pc-plan { padding: 0.85rem 0.9rem; border-radius: 16px; border: 1px solid var(--portal-border); background: var(--portal-bg); text-align: left; min-width: 0; }
    .pc-plan--to { border: 2px solid var(--portal-primary); background: #fff; box-shadow: 0 10px 24px rgba(36, 67, 115, 0.14); }
    .pc-plan__tag { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--portal-muted); }
    .pc-plan__name { font-weight: 800; color: var(--portal-text); line-height: 1.2; margin-top: 0.15rem; }
    .pc-plan__price { font-size: 0.8rem; color: var(--portal-muted); font-weight: 600; }
    .pc-arrow { width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); color: #fff; font-size: 0.8rem; box-shadow: 0 6px 14px rgba(36, 67, 115, 0.3); }

    .pc-body { padding: 1.25rem 1.5rem 0; }
    .pc-due { text-align: center; padding: 1.1rem; border-radius: 18px; background: linear-gradient(135deg, #f0f5ff, #fdf2f5); border: 1px solid #e3e9f7; }
    .pc-due__label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--portal-muted); }
    .pc-due__amount { font-size: 2.1rem; font-weight: 800; line-height: 1.1; color: var(--portal-text); letter-spacing: -0.02em; }
    .pc-due__amount small { font-size: 1rem; font-weight: 700; color: var(--portal-muted); margin-right: 0.25rem; }
    .pc-due__sub { font-size: 0.8rem; color: var(--portal-muted); margin-top: 0.2rem; }

    .pc-lines { list-style: none; padding: 0; margin: 1rem 0 0; }
    .pc-lines li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.55rem 0; font-size: 0.88rem; color: #3b3f5c; }
    .pc-lines li + li { border-top: 1px dashed var(--portal-border); }
    .pc-lines li span:first-child { display: flex; align-items: center; gap: 0.55rem; }
    .pc-lines li i { width: 1.6rem; height: 1.6rem; flex-shrink: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; background: var(--portal-bg); color: var(--portal-primary); }
    .pc-lines li.is-credit i { background: #dcfce7; color: #16a34a; }
    .pc-lines li strong { white-space: nowrap; }
    .pc-lines li.is-credit strong { color: #16a34a; }

    .pc-timeline { margin-top: 1rem; padding: 0.9rem 1rem; border-radius: 14px; background: var(--portal-bg); }
    .pc-step { display: flex; gap: 0.75rem; font-size: 0.84rem; color: #3b3f5c; }
    .pc-step + .pc-step { margin-top: 0.7rem; }
    .pc-step__dot { width: 0.7rem; height: 0.7rem; margin-top: 0.3rem; flex-shrink: 0; border-radius: 50%; background: var(--portal-primary); box-shadow: 0 0 0 4px rgba(36, 67, 115, 0.12); }
    .pc-step__dot--muted { background: #c3c9dc; box-shadow: 0 0 0 4px rgba(195, 201, 220, 0.25); }
    .pc-step strong { color: var(--portal-text); }

    .pc-foot { padding: 1.25rem 1.5rem 1.5rem; }
    .pc-confirm { width: 100%; padding: 0.85rem 1rem; border: 0; border-radius: 14px; font-weight: 800; font-size: 0.95rem; color: #fff; background: linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); box-shadow: 0 14px 28px rgba(36, 67, 115, 0.3); transition: transform 0.15s ease; }
    .pc-confirm:hover:not(:disabled) { transform: translateY(-2px); }
    .pc-confirm:disabled { opacity: 0.6; }
    .pc-confirm--danger { background: linear-gradient(135deg, #dc2626, #b91c1c); box-shadow: 0 14px 28px rgba(220, 38, 38, 0.25); }
    .pc-cancel { display: block; width: 100%; margin-top: 0.6rem; border: 0; background: none; color: var(--portal-muted); font-weight: 700; font-size: 0.88rem; }
    .pc-secure { text-align: center; font-size: 0.72rem; color: var(--portal-muted); margin-top: 0.75rem; }
    @media (max-width: 480px) { .pc-flow { grid-template-columns: 1fr; } .pc-arrow { transform: rotate(90deg); margin: 0 auto; } }
</style>
@endpush

@php
    $subscribed = $stripeEnabled && $owner->hasStripeSubscription();
    $currentInterval = $owner->billing_interval ?: 'monthly';
    $defaultInterval = $subscribed ? $currentInterval : 'monthly';
    $maxSaving = $plans->max(fn ($p) => $p->yearlySavingsPercent());
    $billing = $stripeEnabled ? app(\App\Services\StripeBillingService::class) : null;
    $intervalWord = fn ($i) => $i === 'yearly' ? 'year' : 'month';
@endphp

@section('content')
@php
    $scheduled = $subscribed ? $owner->scheduledPlan : null;
    $subStatus = $subscribed ? ([
        'active' => ['pl-sub__badge--ok', 'Active'], 'trialing' => ['pl-sub__badge--ok', 'Trial'],
        'past_due' => ['pl-sub__badge--warn', 'Payment failed — retrying'], 'unpaid' => ['pl-sub__badge--warn', 'Unpaid'],
    ][$owner->subscription_status] ?? ['pl-sub__badge--warn', ucfirst((string) $owner->subscription_status)]) : null;
@endphp

{{-- One compact summary bar (current plan, usage, subscription, billing actions) so the plans stay in view. --}}
<div class="pl-bar mb-3">
    <div class="pl-bar__main">
        <span class="pl-bar__icon"><i class="fas fa-layer-group"></i></span>
        <div class="min-w-0">
            <div class="pl-bar__title">
                <span>{{ $currentPlan?->getTranslation('name') ?? 'No plan' }}@if($currentPlan && (float) $currentPlan->price > 0) · {{ ucfirst($currentInterval) }}@endif</span>
                @if($subStatus)<span class="pl-sub__badge {{ $subStatus[0] }}">{{ $subStatus[1] }}</span>@endif
                @if($pendingRequest)<span class="pl-sub__badge pl-sub__badge--warn"><i class="fas fa-clock me-1"></i>{{ $pendingRequest->plan->getTranslation('name') }} pending review</span>@endif
            </div>
            <div class="pl-bar__meta">
                <span class="pl-bar__usage" title="Properties and Commercial combined">
                    <span class="pl-bar__meter"><span class="{{ $limit && $used >= $limit ? 'is-full' : '' }}" style="width: {{ $usagePct }}%;"></span></span>
                    {{ $used }}{{ $limit ? ' / ' . $limit : '' }} listings{{ $limit ? '' : ' · unlimited' }}
                </span>
                @if($subscribed)
                <span class="pl-bar__sep" aria-hidden="true">·</span>
                <span>
                    @if($owner->subscription_cancel_at_period_end)
                        Auto-renew off — ends <strong>{{ $owner->subscription_renews_at?->format('d M Y') }}</strong>, then Free
                    @elseif($scheduled)
                        <strong>{{ $scheduled->getTranslation('name') }} ({{ $owner->scheduled_interval ?: 'monthly' }})</strong> starts <strong>{{ $owner->subscription_renews_at?->format('d M Y') }}</strong> at AED {{ number_format($scheduled->priceFor($owner->scheduled_interval ?: 'monthly'), 2) }}/{{ $intervalWord($owner->scheduled_interval ?: 'monthly') }}
                    @else
                        Renews <strong>{{ $owner->subscription_renews_at?->format('d M Y') ?? '—' }}</strong> · AED {{ number_format($currentPlan?->priceFor($currentInterval) ?? 0, 2) }}
                    @endif
                    @if($owner->subscription_status === 'past_due') — update your card to keep your plan. @endif
                </span>
                @elseif(!$stripeEnabled)
                <span class="pl-bar__sep" aria-hidden="true">·</span>
                <span>Upgrades are activated by our team after a quick review.</span>
                @endif
            </div>
        </div>
    </div>
    <div class="pl-bar__actions">
        @if($hasPayments)
        <a href="{{ route('portal.plans.payments') }}" class="btn btn-portal-light btn-sm"><i class="fas fa-receipt me-1"></i>Payment history</a>
        @endif
        @if($subscribed)
        @if($scheduled)
        <form action="{{ route('portal.plans.subscription.keep-plan') }}" method="POST">@csrf
            <button class="btn btn-portal-primary btn-sm"><i class="fas fa-undo me-1"></i>Keep {{ $currentPlan?->getTranslation('name') }}</button>
        </form>
        @elseif($owner->subscription_cancel_at_period_end)
        <form action="{{ route('portal.plans.subscription.resume') }}" method="POST">@csrf
            <button class="btn btn-portal-primary btn-sm"><i class="fas fa-redo me-1"></i>Resume auto-renew</button>
        </form>
        @else
        <form action="{{ route('portal.plans.subscription.cancel') }}" method="POST"
              data-confirm-title="Turn off auto-renew?" data-confirm="You keep your plan until {{ $owner->subscription_renews_at?->format('d M Y') ?? 'the end of the period you already paid for' }}, then move to Free."
              data-confirm-ok="Turn off" data-confirm-tone="warning">@csrf
            <button class="btn btn-portal-light btn-sm text-danger"><i class="fas fa-ban me-1"></i>Cancel auto-renew</button>
        </form>
        @endif
        @endif
    </div>
</div>

@if(request('checkout') === 'cancelled')
<div class="alert alert-warning py-2"><i class="fas fa-info-circle me-2"></i>Checkout was cancelled — you haven't been charged.</div>
@endif

@if($errors->has('coupon_code'))
<div class="alert alert-danger py-2"><i class="fas fa-ticket-alt me-2"></i>{{ $errors->first('coupon_code') }}</div>
@endif

{{-- Billing period toggle + coupon on one line --}}
@if($hasYearly || !$pendingRequest)
<div class="pl-controls">
    @if($hasYearly)
    <div class="pl-toggle" role="tablist" aria-label="Billing period">
        <button type="button" data-interval-toggle="monthly" class="{{ $defaultInterval === 'monthly' ? 'is-active' : '' }}">Monthly</button>
        <button type="button" data-interval-toggle="yearly" class="{{ $defaultInterval === 'yearly' ? 'is-active' : '' }}">
            Yearly @if($maxSaving > 0)<span class="pl-toggle__save">Save up to {{ $maxSaving }}%</span>@endif
        </button>
    </div>
    @endif
    @unless($pendingRequest)
    <div class="pl-coupon">
        <form id="couponForm" class="pl-coupon__form">
            <span class="pl-coupon__icon" aria-hidden="true"><i class="fas fa-ticket-alt"></i></span>
            <input type="text" id="couponInput" class="form-control text-uppercase" placeholder="Coupon code" aria-label="Coupon code" maxlength="40" value="{{ old('coupon_code') }}" autocomplete="off">
            <button type="submit" class="btn btn-portal-primary btn-sm" id="couponApply">Apply</button>
            <button type="button" class="btn btn-portal-light btn-sm d-none" id="couponRemove">Remove</button>
        </form>
        <div class="pl-coupon__status" id="couponStatus">Have a coupon? Apply it to see discounted prices.</div>
    </div>
    @endunless
</div>
@endif


<div class="pl-grid">
    @foreach($plans as $plan)
    @php
        $popular = $plan->is_popular;
        $isCurrentPlan = $owner->plan_id === $plan->id;
    @endphp
    <div class="pl-card {{ $popular ? 'pl-card--popular' : '' }} {{ $isCurrentPlan && !$popular ? 'pl-card--current' : '' }}">
        @if($isCurrentPlan)
        <span class="pl-ribbon pl-ribbon--current"><i class="fas fa-check-circle me-1"></i>Your Plan</span>
        @elseif($popular)
        <span class="pl-ribbon"><i class="fas fa-fire me-1"></i>Most Popular</span>
        @endif

        <div class="pl-icon"><i class="fas {{ $tierIcons[min($loop->index, count($tierIcons) - 1)] }}"></i></div>
        <h3 class="pl-name">{{ $plan->getTranslation('name') }}</h3>
        <p class="pl-desc">{{ $plan->getTranslation('description') }}</p>

        @foreach(\App\Models\Plan::INTERVALS as $interval)
        @php
            // A plan without a yearly price keeps showing (and selling) monthly under the Yearly tab.
            $effective = $interval === 'yearly' && !$plan->hasYearly() ? 'monthly' : $interval;
            $isFree = (float) $plan->price <= 0;
        @endphp
        <div data-interval-block="{{ $interval }}" @if($interval !== $defaultInterval) hidden @endif>
            <div class="pl-price" data-plan-price="{{ $plan->id }}-{{ $interval }}">
                @if($isFree)
                <span class="pl-price__amount">Free</span>
                <span class="pl-price__cycle">forever</span>
                @else
                <span class="pl-price__currency">AED</span>
                <span class="pl-price__amount">{{ number_format($plan->priceFor($effective), 0) }}</span>
                <span class="pl-price__cycle">/ {{ $intervalWord($effective) }}</span>
                @endif
            </div>
            @if($interval === 'yearly' && !$isFree)
                @if($plan->hasYearly())
                <div class="pl-yearly-note">≈ AED {{ number_format($plan->yearly_price / 12, 0) }}/month @if($plan->yearlySavingsPercent() > 0)· save {{ $plan->yearlySavingsPercent() }}%@endif</div>
                @else
                <div class="pl-yearly-note is-muted">Monthly billing only</div>
                @endif
            @endif
            <div class="pl-discount d-none" data-plan-discount="{{ $plan->id }}-{{ $interval }}"></div>
        </div>
        @endforeach

        <ul class="pl-features">
            @foreach($plan->entitlementLines() as [$text, $included])
                @if($included)
                <li class="{{ $loop->first ? 'is-highlight' : '' }}"><span class="pl-check"><i class="fas fa-check"></i></span>{{ $text }}</li>
                @endif
            @endforeach
            @foreach($plan->features as $feature)
            <li><span class="pl-check"><i class="fas fa-check"></i></span>{{ $feature }}</li>
            @endforeach
            @foreach($plan->entitlementLines() as [$text, $included])
                @unless($included)
                <li class="is-excluded"><span class="pl-check"><i class="fas fa-times"></i></span>{{ $text }}</li>
                @endunless
            @endforeach
        </ul>

        @foreach(\App\Models\Plan::INTERVALS as $interval)
        @php
            $effective = $interval === 'yearly' && !$plan->hasYearly() ? 'monthly' : $interval;
            $isFree = (float) $plan->price <= 0;
            $isCurrent = $isCurrentPlan && ($isFree || !$subscribed || $effective === $currentInterval);
            $isScheduled = $subscribed && $owner->scheduled_plan_id === $plan->id && ($owner->scheduled_interval ?: 'monthly') === $effective;
            $online = \App\Services\StripeBillingService::supportsPlan($plan, $effective);
            $change = $subscribed && ($online || $isFree) ? $billing->classifyChange($owner, $plan, $effective) : null;
            $isPending = $pendingRequest && $pendingRequest->plan_id === $plan->id;
        @endphp
        <div data-interval-block="{{ $interval }}" @if($interval !== $defaultInterval) hidden @endif>
            @if($isCurrent)
            <button type="button" class="pl-btn pl-btn--current" disabled><i class="fas fa-check me-1"></i>Current Plan</button>
            @elseif($isScheduled || ($isFree && $subscribed && $owner->subscription_cancel_at_period_end))
            <button type="button" class="pl-btn pl-btn--muted" disabled><i class="fas fa-calendar-alt me-1"></i>Starts {{ $owner->subscription_renews_at?->format('d M') }}</button>
            @elseif($change && !$pendingRequest)
            <button type="button" class="pl-btn {{ $popular ? 'pl-btn--light' : 'pl-btn--primary' }} js-plan-change"
                    data-plan="{{ $plan->id }}" data-interval="{{ $effective }}">
                @if($change === 'cancel')
                    Switch to {{ $plan->getTranslation('name') }}
                @elseif($change === 'upgrade')
                    <i class="fas fa-arrow-up me-1"></i>Upgrade
                @else
                    <i class="fas fa-arrow-down me-1"></i>Downgrade
                @endif
            </button>
            @elseif($online && !$subscribed && !$pendingRequest)
            <form action="{{ route('portal.plans.checkout') }}" method="GET" class="js-plan-form" data-no-spinner="1">
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <input type="hidden" name="interval" value="{{ $effective }}">
                <input type="hidden" name="coupon_code" value="">
                <button type="submit" class="pl-btn {{ $popular ? 'pl-btn--light' : 'pl-btn--primary' }}"><i class="fas fa-lock me-1"></i>Subscribe &amp; Pay</button>
            </form>
            @elseif($isPending)
            <button type="button" class="pl-btn pl-btn--muted" disabled><i class="fas fa-hourglass-half me-1"></i>Request Pending</button>
            @elseif($pendingRequest)
            <button type="button" class="pl-btn pl-btn--muted" disabled title="You already have a pending request">Upgrade</button>
            @else
            <form action="{{ route('portal.plans.request') }}" method="POST" class="js-plan-form">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <input type="hidden" name="interval" value="{{ $effective }}">
                <input type="hidden" name="coupon_code" value="">
                <button type="submit" class="pl-btn {{ $popular ? 'pl-btn--light' : 'pl-btn--primary' }}">
                    {{ $currentPlan && $plan->price < $currentPlan->price ? 'Switch Plan' : 'Upgrade Now' }} <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </form>
            @endif
        </div>
        @endforeach
    </div>
    @endforeach
</div>

{{-- Plan change confirmation: shows exactly what will be charged (Stripe preview) before switching. --}}
<div class="modal fade pl-modal" id="planChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="pc-top">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <span class="pc-kind" id="pcKind"></span>
                <h5 class="pc-title" id="pcTitle">Change plan</h5>
            </div>

            <div class="pc-flow">
                <div class="pc-plan">
                    <div class="pc-plan__tag">Current</div>
                    <div class="pc-plan__name" id="pcFrom"></div>
                    <div class="pc-plan__price" id="pcFromPrice"></div>
                </div>
                <span class="pc-arrow"><i class="fas fa-arrow-right"></i></span>
                <div class="pc-plan pc-plan--to">
                    <div class="pc-plan__tag">New</div>
                    <div class="pc-plan__name" id="pcTo"></div>
                    <div class="pc-plan__price" id="pcToPrice"></div>
                </div>
            </div>

            <div class="pc-body">
                <div id="pcLoading" class="text-center py-4 portal-muted"><span class="spinner-border spinner-border-sm me-2"></span>Calculating with Stripe…</div>
                <div id="pcBody" class="d-none"></div>
                <div id="pcError" class="alert alert-danger d-none mb-0"></div>
            </div>

            <div class="pc-foot">
                <form action="{{ route('portal.plans.request') }}" method="POST" id="pcForm" class="m-0">
                    @csrf
                    <input type="hidden" name="plan_id" id="pcPlan">
                    <input type="hidden" name="interval" id="pcInterval">
                    <input type="hidden" name="coupon_code" id="pcCoupon">
                    <input type="hidden" name="proration_date" id="pcProration">
                    <button type="submit" class="pc-confirm" id="pcConfirm" disabled>Confirm</button>
                </form>
                <button type="button" class="pc-cancel" data-bs-dismiss="modal">Not now</button>
                <div class="pc-secure" id="pcSecure"><i class="fas fa-lock me-1"></i>Charged securely to your saved card via Stripe</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const csrf = '{{ csrf_token() }}';
        const fmt = n => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        let coupon = null; // { code, plans: { planId: { monthly: {...}, yearly: {...} } } }

        // ---- Monthly / Yearly toggle
        document.querySelectorAll('[data-interval-toggle]').forEach(btn => btn.addEventListener('click', function () {
            const interval = btn.dataset.intervalToggle;
            document.querySelectorAll('[data-interval-toggle]').forEach(b => b.classList.toggle('is-active', b === btn));
            document.querySelectorAll('[data-interval-block]').forEach(el => { el.hidden = el.dataset.intervalBlock !== interval; });
        }));

        const couponFor = (planId, interval) => {
            const p = coupon?.plans?.[planId]?.[interval];
            return p && p.valid ? coupon.code : '';
        };

        // Direct forms (Stripe Checkout / manual request): attach the applied coupon if it fits.
        document.querySelectorAll('.js-plan-form').forEach(f => f.addEventListener('submit', function () {
            f.querySelector('[name="coupon_code"]').value = couponFor(f.querySelector('[name="plan_id"]').value, f.querySelector('[name="interval"]').value);
        }));

        // ---- Change-plan modal (subscribed accounts)
        const modalEl = document.getElementById('planChangeModal');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const $ = id => document.getElementById(id);

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-plan-change');
            if (!btn || !modal) return;
            const planId = btn.dataset.plan, interval = btn.dataset.interval, code = couponFor(planId, interval);
            $('pcPlan').value = planId; $('pcInterval').value = interval; $('pcCoupon').value = code; $('pcProration').value = '';
            $('pcLoading').classList.remove('d-none'); $('pcBody').classList.add('d-none'); $('pcError').classList.add('d-none');
            $('pcConfirm').disabled = true;
            modal.show();

            fetch(@json(route('portal.plans.change-preview')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: planId, interval, coupon_code: code || null }),
            })
                .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok || !d.success) throw new Error(d.message || Object.values(d.errors || {})[0]?.[0] || 'Could not calculate this change.'); return d; })
                .then(d => render(d))
                .catch(err => { $('pcLoading').classList.add('d-none'); $('pcError').textContent = err.message; $('pcError').classList.remove('d-none'); });
        });

        const unitOf = i => (i === 'yearly' ? 'yr' : 'mo');
        const due = (label, amount, sub) => `<div class="pc-due"><div class="pc-due__label">${label}</div><div class="pc-due__amount"><small>AED</small>${fmt(amount)}</div>${sub ? `<div class="pc-due__sub">${sub}</div>` : ''}</div>`;
        const step = (text, muted) => `<div class="pc-step"><span class="pc-step__dot ${muted ? 'pc-step__dot--muted' : ''}"></span><div>${text}</div></div>`;

        function render(d) {
            const unit = d.interval === 'yearly' ? 'year' : 'month';
            $('pcFrom').textContent = d.current_plan || '—';
            $('pcFromPrice').textContent = `AED ${fmt(d.current_price)}/${unitOf(d.current_interval)} · ${d.current_interval}`;
            $('pcTo').textContent = d.plan;
            $('pcToPrice').textContent = d.new_price > 0 ? `AED ${fmt(d.new_price)}/${unitOf(d.interval)} · ${d.interval}` : 'Free';
            const kind = $('pcKind');
            const confirm = $('pcConfirm');
            confirm.classList.remove('pc-confirm--danger');
            $('pcSecure').classList.toggle('d-none', d.type !== 'upgrade');
            let html = '';

            if (d.type === 'upgrade') {
                kind.className = 'pc-kind pc-kind--upgrade'; kind.innerHTML = '<i class="fas fa-arrow-up"></i> Upgrade';
                $('pcTitle').textContent = `Upgrade to ${d.plan}`;
                html += due('Charged today', d.due_now, 'Your new plan is active right after payment');
                html += '<ul class="pc-lines">';
                d.lines.forEach(l => {
                    const credit = l.amount < 0;
                    html += `<li class="${credit ? 'is-credit' : ''}"><span><i class="fas ${credit ? 'fa-undo-alt' : 'fa-layer-group'}"></i>${esc(l.description)}</span><strong>${credit ? '−' : ''}AED ${fmt(Math.abs(l.amount))}</strong></li>`;
                });
                if (d.discount > 0) html += `<li class="is-credit"><span><i class="fas fa-ticket-alt"></i>Coupon ${esc(d.coupon || '')}</span><strong>−AED ${fmt(d.discount)}</strong></li>`;
                if (d.credit > 0) html += `<li class="is-credit"><span><i class="fas fa-wallet"></i>Account credit</span><strong>−AED ${fmt(d.credit)}</strong></li>`;
                html += '</ul>';
                html += `<div class="pc-timeline">${step('<strong>Today</strong> — new billing period starts')}${step(`<strong>${esc(d.next_date)}</strong> — next payment AED ${fmt(d.next_amount)}, then every ${unit}`, true)}</div>`;
                $('pcProration').value = d.proration_date || '';
                confirm.innerHTML = `<i class="fas fa-lock me-1"></i> Pay AED ${fmt(d.due_now)} & upgrade`;
            } else if (d.type === 'downgrade') {
                kind.className = 'pc-kind pc-kind--downgrade'; kind.innerHTML = '<i class="fas fa-arrow-down"></i> Downgrade';
                $('pcTitle').textContent = `Switch to ${d.plan}`;
                html += due('Charged today', 0, 'No charge or refund — you keep what you paid for');
                html += `<div class="pc-timeline">${step(`<strong>Now → ${esc(d.effective_date)}</strong> — you stay on ${esc(d.current_plan)}`)}${step(`<strong>${esc(d.next_date)}</strong> — ${esc(d.plan)} starts at AED ${fmt(d.next_amount)}/${unit}`, true)}${step('You can undo this any time before then', true)}</div>`;
                confirm.innerHTML = '<i class="fas fa-calendar-check me-1"></i> Schedule downgrade';
            } else if (d.type === 'cancel') {
                kind.className = 'pc-kind pc-kind--cancel'; kind.innerHTML = '<i class="fas fa-ban"></i> Cancel subscription';
                $('pcTitle').textContent = `Move to ${d.plan}`;
                html += due('Charged today', 0, 'Auto-renew stops — no refund for the current period');
                html += `<div class="pc-timeline">${step(`<strong>Now → ${esc(d.effective_date)}</strong> — you keep ${esc(d.current_plan)}`)}${step(`<strong>${esc(d.effective_date)}</strong> — account moves to ${esc(d.plan)}`, true)}</div>`;
                confirm.classList.add('pc-confirm--danger');
                confirm.innerHTML = 'Yes, stop auto-renew';
            } else {
                kind.className = 'pc-kind'; kind.textContent = '';
                html = '<div class="pc-timeline">You are already on this plan.</div>';
            }

            $('pcBody').innerHTML = html;
            $('pcLoading').classList.add('d-none'); $('pcBody').classList.remove('d-none');
            confirm.disabled = d.type === 'same';
        }
        $('pcForm')?.addEventListener('submit', () => { $('pcConfirm').disabled = true; });

        // ---- Coupon preview
        const form = $('couponForm');
        if (!form) return;
        const input = $('couponInput'), status = $('couponStatus'), box = form.closest('.pl-coupon'), applyBtn = $('couponApply'), removeBtn = $('couponRemove');
        const originals = {};
        document.querySelectorAll('[data-plan-price]').forEach(el => { originals[el.dataset.planPrice] = el.innerHTML; });

        function reset() {
            coupon = null;
            Object.entries(originals).forEach(([key, html]) => { document.querySelector(`[data-plan-price="${key}"]`).innerHTML = html; });
            document.querySelectorAll('[data-plan-discount]').forEach(el => { el.classList.add('d-none'); el.textContent = ''; });
            box.classList.remove('is-applied'); removeBtn.classList.add('d-none'); input.readOnly = false;
            status.textContent = 'Apply it to see your discounted price before choosing a plan.';
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const code = input.value.trim();
            if (!code) return;
            applyBtn.disabled = true;
            fetch(@json(route('portal.plans.coupon-check')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ coupon_code: code }),
            })
                .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok || !d.success) throw new Error(d.message || Object.values(d.errors || {})[0]?.[0] || 'This coupon code is not valid.'); return d; })
                .then(data => {
                    reset();
                    coupon = { code: data.code, plans: data.plans };
                    Object.entries(data.plans).forEach(([id, byInterval]) => {
                        ['monthly', 'yearly'].forEach(tab => {
                            // Yearly tab of a monthly-only plan shows the monthly result.
                            const p = byInterval[tab] || (tab === 'yearly' ? byInterval.monthly : null);
                            const note = document.querySelector(`[data-plan-discount="${id}-${tab}"]`);
                            if (!p || !note) return;
                            note.classList.remove('d-none');
                            if (p.valid) {
                                note.classList.remove('is-invalid');
                                note.innerHTML = `<i class="fas fa-tag me-1"></i>${esc(data.code)}: ${esc(p.label)} — save AED ${fmt(p.discount)}`;
                                const amount = document.querySelector(`[data-plan-price="${id}-${tab}"] .pl-price__amount`);
                                if (amount) amount.innerHTML = Number(p.final).toLocaleString('en-US', { maximumFractionDigits: 2 }) + `<span class="pl-price__old">${Number(p.original).toLocaleString('en-US')}</span>`;
                            } else {
                                note.classList.add('is-invalid');
                                note.innerHTML = `<i class="fas fa-info-circle me-1"></i>${esc(p.message)}`;
                            }
                        });
                    });
                    box.classList.add('is-applied'); removeBtn.classList.remove('d-none');
                    input.value = data.code; input.readOnly = true;
                    status.innerHTML = `<span class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Coupon ${esc(data.code)} applied.</span> It's used on the plan you choose below.`;
                })
                .catch(err => { reset(); status.innerHTML = `<span class="text-danger fw-semibold"><i class="fas fa-times-circle me-1"></i>${esc(err.message)}</span>`; })
                .finally(() => { applyBtn.disabled = false; });
        });

        removeBtn.addEventListener('click', function () { input.value = ''; reset(); input.focus(); });
        if (input.value) form.requestSubmit();
    })();
</script>
@endpush

<p class="pl-note"><i class="fas fa-shield-alt me-1"></i> {{ $stripeEnabled ? "Payments are processed securely by Stripe — MW Realty never sees your card details." : "Plan changes are reviewed by MW Realty and applied to your account — no payment is taken on this page." }}</p>
@endsection
