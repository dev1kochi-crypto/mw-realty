@extends('portal.layouts.app')

@section('title', 'Reports')

@section('content')
<div class="portal-section-title mb-3">Reports</div>

<div class="portal-card p-5 text-center" style="max-width: 640px; margin: 2rem auto;">
    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
         style="width: 72px; height: 72px; border-radius: 20px; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); color: #fff; font-size: 1.8rem;">
        <i class="fas fa-chart-line"></i>
    </div>
    <h4 class="fw-bold mb-2">Unlock leads reports &amp; analytics</h4>
    <p class="portal-muted mb-4">
        See leads by stage and status, conversion rate and lead trends over time.
        Reports aren't included in your {{ $plan?->getTranslation('name') ?? 'current' }} plan.
    </p>
    <a href="{{ route('portal.plans.index') }}" class="btn btn-portal-primary"><i class="fas fa-rocket me-2"></i>View Plans</a>
</div>
@endsection
