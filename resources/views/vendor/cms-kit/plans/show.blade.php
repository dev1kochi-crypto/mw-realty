@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.plans.index') }}">Plans</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $plan->getTranslation('name') }}</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --dash-navy: var(--secondary-color, #264373);
        --dash-teal: var(--primary-color, #04a1cc);
        --dash-green: #0f9d58;
        --dash-green-2: #34c880;
        --dash-amber: #e08e0b;
        --dash-amber-2: #f5a623;
        --dash-slate: #5b6478;
        --dash-ink: #1c2340;
        --dash-muted: #838aa3;
        --dash-bg-soft: #f7f8fc;
        --dash-border: rgba(28, 35, 64, 0.07);
    }
    .fill-teal  { background: linear-gradient(135deg, var(--dash-teal), #2fc4e8); }
    .fill-navy  { background: linear-gradient(135deg, var(--dash-navy), #3a5794); }
    .fill-green { background: linear-gradient(135deg, var(--dash-green), var(--dash-green-2)); }
    .fill-amber { background: linear-gradient(135deg, var(--dash-amber), var(--dash-amber-2)); }

    .profile-hero {
        background: linear-gradient(120deg, var(--dash-navy) 0%, #16294f 55%, var(--dash-teal) 145%);
        border-radius: 16px; padding: 1.75rem 2rem; color: #fff; margin-bottom: 1.25rem;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;
    }
    .profile-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.35rem; margin-bottom: 0.3rem; }
    .profile-meta { display: flex; flex-wrap: wrap; gap: 1.1rem; margin-top: 0.55rem; font-size: 0.86rem; opacity: 0.9; }
    .profile-meta span i { width: 16px; opacity: 0.8; }
    .profile-actions .btn { font-weight: 700; font-size: 0.83rem; background: #fff; color: var(--dash-navy); border: none; }
    .profile-actions .btn:hover { opacity: 0.92; color: var(--dash-navy); }

    .stat-mini {
        border-radius: 14px; border: 1px solid var(--dash-border); background: #fff;
        box-shadow: 0 4px 14px rgba(28,35,64,0.05);
        padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%;
    }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; }
    .stat-mini-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: var(--dash-ink); }
    .stat-mini-label { font-size: 0.68rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; letter-spacing: 0.02em; }

    .dash-card { border-radius: 16px; border: 1px solid var(--dash-border); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 1.25rem 1.4rem; }
    .dash-card h6 { font-weight: 800; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 1.1rem; color: var(--dash-navy); display: flex; align-items: center; gap: 0.5rem; }
    .dash-card h6 i { color: var(--dash-muted); font-size: 0.85rem; }

    .mini-table { width: 100%; font-size: 0.83rem; }
    .mini-table td, .mini-table th { padding: 0.6rem 0.4rem; vertical-align: middle; }
    .mini-table thead th { font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.02em; color: var(--dash-muted); border-bottom: 1.5px solid var(--dash-border); font-weight: 800; }
    .mini-table tbody tr { border-bottom: 1px solid #f5f6fa; }
    .mini-table tbody tr:last-child { border-bottom: none; }

    .subscriber-avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.68rem; flex-shrink: 0; }
</style>
@endpush

@section('content')
@php
    $priceLabel = $plan->billing_cycle === 'free' ? 'Free' : 'AED ' . number_format($plan->price);
    $suffix = ['monthly' => '/mo', 'yearly' => '/yr', 'one_time' => ' one-time'][$plan->billing_cycle] ?? '';
    $limitLabel = $plan->isUnlimited() ? 'Unlimited properties' : $plan->property_limit . ' properties';
    $statusMap = ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'];
@endphp

<div class="profile-hero">
    <div>
        <div class="profile-name">{{ $plan->getTranslation('name') }} @if($plan->is_popular)<span class="badge bg-warning text-dark ms-1">Popular</span>@endif</div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border-0">{{ $priceLabel }}{{ $suffix }}</span>
            <span class="badge {{ $plan->status ? 'bg-success' : 'bg-secondary' }}">{{ $plan->status ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="profile-meta">
            <span><i class="fas fa-building"></i> {{ $limitLabel }}</span>
            <span><i class="fas fa-sync"></i> {{ ucfirst(str_replace('_', ' ', $plan->billing_cycle)) }} billing</span>
        </div>
    </div>
    <div class="profile-actions d-flex gap-2">
        @can('plans.edit')
        <a href="{{ route('cms.plans.edit', $plan->id) }}" class="btn"><i class="fas fa-edit me-1"></i> Edit Plan</a>
        @endcan
        <a href="{{ route('cms.portal-accounts.index', ['plan_id' => $plan->id]) }}" class="btn"><i class="fas fa-users me-1"></i> View in Agents & Companies</a>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-navy"><i class="fas fa-users"></i></span>
            <div><div class="stat-mini-value">{{ $plan->subscribers_count }}</div><div class="stat-mini-label">Subscribers</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-green"><i class="fas fa-user-check"></i></span>
            <div><div class="stat-mini-value">{{ $activeSubscribers }}</div><div class="stat-mini-label">Approved</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-amber"><i class="fas fa-coins"></i></span>
            <div><div class="stat-mini-value">AED {{ number_format($totalCollected) }}</div><div class="stat-mini-label">Total Collected</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-mini">
            <span class="stat-mini-icon fill-teal"><i class="fas fa-receipt"></i></span>
            <div><div class="stat-mini-value">{{ $recentPayments->total() }}</div><div class="stat-mini-label">Total Payments</div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="dash-card">
            <h6><i class="fas fa-people-group"></i> Connected Agents & Companies ({{ $subscribers->total() }})</h6>
            <div class="table-responsive">
                <table class="mini-table">
                    <thead>
                        <tr><th>Name</th><th>Type</th><th>Status</th><th>Payment</th><th>Last Payment</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($subscribers as $subscriber)
                        @php
                            $label = $subscriber->type === 'company' ? ($subscriber->company_name ?: $subscriber->name) : $subscriber->name;
                            $initials = strtoupper(collect(preg_split('/\s+/', trim($label)))->filter()->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
                            $fill = $subscriber->type === 'company' ? 'fill-navy' : 'fill-teal';
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="subscriber-avatar {{ $fill }}">{{ $initials }}</span>
                                    <a href="{{ route('cms.portal-accounts.show', ['id' => $subscriber->id, 'type' => $subscriber->type]) }}" class="fw-semibold text-decoration-none">{{ $label }}</a>
                                </div>
                            </td>
                            <td>{{ ucfirst($subscriber->type) }}</td>
                            <td><span class="badge {{ $statusMap[$subscriber->status] ?? 'bg-secondary' }}">{{ ucfirst($subscriber->status) }}</span></td>
                            <td>
                                @if($subscriber->payment_status === 'paid')
                                    <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Paid</span>
                                @else
                                    <span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i>Unpaid</span>
                                @endif
                            </td>
                            <td>{{ $subscriber->last_payment_at?->format('d M Y') ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('cms.portal-accounts.show', ['id' => $subscriber->id, 'type' => $subscriber->type]) }}" class="btn btn-sm btn-outline-secondary" title="View payment history"><i class="fas fa-history"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No agents or companies are on this plan yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($subscribers->hasPages())
            <div class="mt-3">{{ $subscribers->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="dash-card">
            <h6><i class="fas fa-receipt"></i> Recent Payments</h6>
            <div class="table-responsive">
                <table class="mini-table">
                    <thead>
                        <tr><th>Subscriber</th><th>Period</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayments as $payment)
                        <tr>
                            <td>
                                @if($payment->portalUser)
                                    <a href="{{ route('cms.portal-accounts.show', ['id' => $payment->portalUser->id, 'type' => $payment->portalUser->type]) }}" class="text-decoration-none">
                                        {{ $payment->portalUser->type === 'company' ? ($payment->portalUser->company_name ?: $payment->portalUser->name) : $payment->portalUser->name }}
                                    </a>
                                @else
                                    <span class="text-muted">Deleted account</span>
                                @endif
                                <div class="text-muted" style="font-size: 0.7rem;">Paid {{ $payment->paid_at->format('d M Y') }}</div>
                            </td>
                            <td>{{ $payment->period_label }}</td>
                            <td class="text-end fw-bold">AED {{ number_format($payment->amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recentPayments->hasPages())
            <div class="mt-3">{{ $recentPayments->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
