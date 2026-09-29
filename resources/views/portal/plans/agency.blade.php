@extends('portal.layouts.app')

@section('title', 'Plans')

{{-- An agency agent is covered by the agency's plan (PortalUser::isOnAgencyPlan) — nothing to buy here. --}}
@php
    $used = $agency->properties()->count();
    $limit = $plan && !$plan->isUnlimited() ? (int) $plan->property_limit : null;
    $usagePct = $limit ? min(100, round($used / max(1, $limit) * 100)) : 100;
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="portal-section-title mb-0">Your Plan</div>
    @if($hasPayments)
    <a href="{{ route('portal.plans.payments') }}" class="btn btn-portal-light btn-sm"><i class="fas fa-receipt me-1"></i>Payment history</a>
    @endif
</div>

<div class="portal-card p-4" style="max-width: 720px;">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="d-flex align-items-center justify-content-center flex-shrink-0"
              style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent, #04a1cc)); color: #fff; font-size: 1.4rem;">
            <i class="fas fa-building-user"></i>
        </span>
        <div class="min-w-0">
            <div class="portal-muted small">Covered by your agency</div>
            <div class="fs-5 fw-bold">{{ $plan?->getTranslation('name') ?? 'No plan' }} <span class="portal-muted fw-normal">· {{ $agency->displayName() }}</span></div>
        </div>
    </div>

    <p class="portal-muted mb-3">
        You're an agent of <strong>{{ $agency->displayName() }}</strong>, so you work on the agency's plan — there's nothing for you to buy or upgrade.
        Listings you add belong to the agency and count towards its plan. If you need more, ask your agency to upgrade.
    </p>

    @if($plan)
    <div class="mb-3">
        <div class="d-flex justify-content-between small fw-semibold mb-1">
            <span>Agency listings</span>
            <span>{{ $used }}{{ $limit ? ' / ' . $limit : '' }}{{ $limit ? '' : ' · unlimited' }}</span>
        </div>
        <div class="progress" style="height: 6px;"><div class="progress-bar" style="width: {{ $usagePct }}%; background: var(--portal-primary);"></div></div>
    </div>

    <ul class="list-unstyled mb-0 row g-2">
        @foreach($plan->entitlementLines() as [$line, $included])
        <li class="col-sm-6 small {{ $included ? '' : 'portal-muted text-decoration-line-through' }}">
            <i class="fas {{ $included ? 'fa-check-circle text-success' : 'fa-times-circle' }} me-1"></i>{{ $line }}
        </li>
        @endforeach
    </ul>
    @endif

    @if($owner->hasStripeSubscription() && $owner->subscription_cancel_at_period_end)
    <div class="alert alert-info small mt-3 mb-0">
        <i class="fas fa-info-circle me-1"></i>Your own subscription won't renew — it ends on <strong>{{ $owner->subscription_renews_at?->format('d M Y') }}</strong> and you won't be charged again.
    </div>
    @endif
</div>
@endsection
