@extends('portal.layouts.app')

@section('title', 'Plans')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="portal-section-title mb-0">Plans</div>
    @if($pendingRequest)
    <span class="portal-badge-status portal-badge-contacted">
        <i class="fas fa-clock me-1"></i> Upgrade to {{ $pendingRequest->plan->getTranslation('name') }} pending review
    </span>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3">
    @foreach($plans as $plan)
    @php($isCurrent = $owner->plan_id === $plan->id)
    <div class="col-md-6 col-lg-4">
        <div class="portal-card p-4 h-100 d-flex flex-column {{ $plan->is_popular ? 'border-primary' : '' }}" style="{{ $plan->is_popular ? 'border-width: 1.5px;' : '' }}">
            @if($plan->is_popular)
            <span class="portal-badge-status portal-badge-new align-self-start mb-2">Most Popular</span>
            @endif
            <div class="fw-bold fs-5">{{ $plan->getTranslation('name') }}</div>
            <div class="text-muted mb-2" style="font-size: 0.85rem;">{{ $plan->getTranslation('description') }}</div>
            <div class="mb-3">
                <span class="fw-bold fs-3">{{ $plan->price == 0 ? 'Free' : number_format($plan->price, 0) }}</span>
                @if($plan->price > 0)
                <span class="text-muted">/ {{ str_replace('_', ' ', $plan->billing_cycle) }}</span>
                @endif
            </div>
            <ul class="list-unstyled flex-grow-1 mb-3" style="font-size: 0.88rem;">
                <li class="mb-1"><i class="fas fa-check text-success me-2"></i>
                    {{ $plan->isUnlimited() ? 'Unlimited listings' : $plan->property_limit . ' listings' }}
                </li>
                @foreach($plan->features as $feature)
                <li class="mb-1"><i class="fas fa-check text-success me-2"></i>{{ $feature }}</li>
                @endforeach
            </ul>

            @if($isCurrent)
            <button type="button" class="btn btn-outline-secondary w-100" disabled>Current Plan</button>
            @elseif($pendingRequest && $pendingRequest->plan_id === $plan->id)
            <button type="button" class="btn btn-outline-secondary w-100" disabled>Request Pending</button>
            @elseif($pendingRequest)
            <button type="button" class="btn btn-outline-secondary w-100" disabled title="You already have a pending request">Upgrade</button>
            @else
            <form action="{{ route('portal.plans.request') }}" method="POST">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <button type="submit" class="btn btn-portal-primary w-100">Upgrade</button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
