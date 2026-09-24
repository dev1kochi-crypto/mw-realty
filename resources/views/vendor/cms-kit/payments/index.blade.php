@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Payments</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .py-stat { border-radius: 14px; border: 1px solid rgba(28,35,64,0.07); background: #fff; box-shadow: 0 4px 14px rgba(28,35,64,0.05); padding: 0.9rem 1rem; display: flex; align-items: center; gap: 0.75rem; height: 100%; }
    .py-stat-icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; }
    .py-stat-value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.2rem; line-height: 1.1; color: #1c2340; }
    .py-stat-label { font-size: 0.68rem; font-weight: 700; color: #838aa3; text-transform: uppercase; letter-spacing: 0.02em; }
    .py-filters { border-radius: 14px; background: #fff; border: 1px solid rgba(28,35,64,0.07); box-shadow: 0 4px 14px rgba(28,35,64,0.05); }
    .py-filters .form-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #838aa3; margin-bottom: 0.3rem; }
    .py-avatar { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 0.78rem; }
    .py-amount { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: #1c2340; }
    .py-method { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; }
    .py-method--stripe { background: rgba(99,91,255,0.1); color: #635bff; }
    .py-method--manual { background: #f1f3f8; color: #5b6478; }
    .py-cycle { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.15rem 0.5rem; border-radius: 6px; }
    .py-cycle--monthly { background: rgba(4,161,204,0.1); color: #0386aa; }
    .py-cycle--yearly { background: rgba(15,157,88,0.1); color: #0f9d58; }
    .py-coupon { font-family: ui-monospace, Menlo, monospace; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 6px; background: #f1f5ff; color: #264373; border: 1px dashed #9fb3dd; }
    .py-status { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; }
    .py-status--paid { background: rgba(15,157,88,0.1); color: #0f9d58; }
    .py-status--failed { background: rgba(220,38,38,0.1); color: #dc2626; cursor: help; }
    .py-quick a { font-size: 0.75rem; font-weight: 700; text-decoration: none; padding: 0.25rem 0.65rem; border-radius: 50px; background: #f1f3f8; color: #5b6478; }
    .py-quick a:hover { background: #e3e8f4; color: #264373; }
</style>
@endpush

@section('content')
@php
    $q = request()->query();
    $range = fn ($from, $to) => route('cms.payments.index', array_merge($q, ['from' => $from, 'to' => $to, 'page' => null]));
@endphp

<div class="row g-2 mb-3">
    @foreach([
        [$filtersActive ? 'Total (filtered)' : 'Total Collected', 'AED ' . number_format($totals->total, 2), 'fa-coins', 'linear-gradient(135deg, #264373, #3a5794)'],
        ['Payments', number_format($totals->cnt), 'fa-receipt', 'linear-gradient(135deg, #04a1cc, #2fc4e8)'],
        ['Paying Accounts', number_format($totals->accounts), 'fa-user-check', 'linear-gradient(135deg, #0f9d58, #34c880)'],
        ['Coupon Discounts', 'AED ' . number_format($totals->discounts, 2), 'fa-ticket-alt', 'linear-gradient(135deg, #e08e0b, #f5a623)'],
        ['Failed', number_format((int) $totals->failed) . ' · AED ' . number_format($totals->failed_amount, 0), 'fa-exclamation-triangle', 'linear-gradient(135deg, #dc2626, #f87171)'],
        ['This Month', 'AED ' . number_format($thisMonth, 2), 'fa-calendar-check', 'linear-gradient(135deg, #6d28d9, #8b5cf6)'],
    ] as [$label, $value, $icon, $bg])
    <div class="col-6 col-lg">
        <div class="py-stat">
            <span class="py-stat-icon" style="background: {{ $bg }};"><i class="fas {{ $icon }}"></i></span>
            <div><div class="py-stat-value">{{ $value }}</div><div class="py-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

<form method="GET" class="py-filters p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Search</label>
            <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Account, email, coupon, invoice">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Plan</label>
            <select name="plan_id" class="form-select form-select-sm">
                <option value="">All plans</option>
                @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->getTranslation('name') }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach(\App\Models\PlanPayment::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Billing</label>
            <select name="cycle" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="monthly" @selected(request('cycle') === 'monthly')>Monthly</option>
                <option value="yearly" @selected(request('cycle') === 'yearly')>Yearly</option>
                <option value="one_time" @selected(request('cycle') === 'one_time')>One-time</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Method</label>
            <select name="method" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="stripe" @selected(request('method') === 'stripe')>Card</option>
                <option value="manual" @selected(request('method') === 'manual')>Manual</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Account</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="agent" @selected(request('type') === 'agent')>Agents</option>
                <option value="company" @selected(request('type') === 'company')>Companies</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Coupon</label>
            <select name="coupon" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="with" @selected(request('coupon') === 'with')>Used</option>
                <option value="without" @selected(request('coupon') === 'without')>None</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-primary btn-sm flex-fill" title="Apply filters"><i class="fas fa-filter"></i></button>
            @if($filtersActive)
            <a href="{{ route('cms.payments.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear filters"><i class="fas fa-times"></i></a>
            @endif
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <div class="py-quick d-flex gap-1 flex-wrap">
            <a href="{{ $range(now()->startOfMonth()->toDateString(), now()->toDateString()) }}">This month</a>
            <a href="{{ $range(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}">Last month</a>
            <a href="{{ $range(now()->subDays(90)->toDateString(), now()->toDateString()) }}">Last 90 days</a>
            <a href="{{ $range(now()->startOfYear()->toDateString(), now()->toDateString()) }}">This year</a>
            <a href="{{ $range(now()->subYear()->toDateString(), now()->toDateString()) }}">Last 12 months</a>
        </div>
        <a href="{{ route('cms.payments.export', request()->query()) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV{{ $filtersActive ? ' (filtered)' : '' }}</a>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Account</th>
                        <th>Plan</th>
                        <th class="text-end">Amount</th>
                        <th>Discount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                    @php
                        $u = $p->portalUser;
                        $label = $u?->displayName() ?? 'Deleted account';
                        $initials = strtoupper(collect(preg_split('/\s+/', trim($label)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
                    @endphp
                    <tr>
                        <td class="ps-4 text-nowrap">
                            <div class="fw-semibold">{{ $p->paid_at?->format('d M Y') }}</div>
                            <div class="text-muted small">{{ $p->created_at?->format('h:i A') }}</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="py-avatar" style="background: {{ $u?->type === 'company' ? 'linear-gradient(135deg,#264373,#3a5794)' : 'linear-gradient(135deg,#04a1cc,#2fc4e8)' }};">{{ $initials }}</span>
                                <div class="min-w-0">
                                    @if($u)
                                    <a href="{{ route('cms.portal-accounts.show', ['id' => $u->id, 'type' => $u->type]) }}" class="fw-semibold text-decoration-none">{{ $label }}</a>
                                    <div class="text-muted small">{{ ucfirst($u->type) }} · {{ $u->email }}</div>
                                    @else
                                    <span class="text-muted fst-italic">{{ $label }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $p->plan_name }}</div>
                            <span class="py-cycle py-cycle--{{ $p->billing_cycle === 'yearly' ? 'yearly' : 'monthly' }}">{{ str_replace('_', ' ', $p->billing_cycle) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="py-amount">AED {{ number_format($p->amount, 2) }}</div>
                            @if($p->original_amount)<div class="text-muted small text-decoration-line-through">AED {{ number_format($p->original_amount, 2) }}</div>@endif
                        </td>
                        <td>
                            @if($p->discount_amount)
                            <div class="small fw-semibold text-success">−AED {{ number_format($p->discount_amount, 2) }}</div>
                            @if($p->coupon_code)<span class="py-coupon">{{ $p->coupon_code }}</span>@endif
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($p->stripe_invoice_id)
                            <span class="py-method py-method--stripe"><i class="fab fa-cc-stripe"></i> Card</span>
                            @else
                            <span class="py-method py-method--manual"><i class="fas fa-user-edit"></i> Manual</span>
                            @endif
                        </td>
                        <td>
                            @if($p->isPaid())
                            <span class="py-status py-status--paid"><i class="fas fa-check-circle"></i> Paid</span>
                            @else
                            <span class="py-status py-status--failed" title="{{ $p->failure_reason }}"><i class="fas fa-exclamation-circle"></i> Failed</span>
                            <div class="text-muted small mt-1">{{ $p->attempts }} attempt{{ $p->attempts === 1 ? '' : 's' }}</div>
                            @endif
                        </td>
                        <td class="text-end pe-4 text-nowrap">
                            <a href="{{ route('cms.payments.show', $p->id) }}" class="btn btn-sm btn-outline-primary" title="View invoice"><i class="fas fa-file-invoice me-1"></i>View</a>
                            <a href="{{ route('cms.payments.pdf', $p->id) }}" class="btn btn-sm btn-outline-danger" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="fas fa-receipt fa-2x mb-2 d-block opacity-50"></i>
                            {{ $filtersActive ? 'No payments match these filters.' : 'No payments yet.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $payments->links('pagination::bootstrap-5') }}</div>
@endsection
