@extends('cms-kit::layouts.cms')

@section('title', 'Credit History')

@section('breadcrumbs')
    @if($cmsUser->can('credits.view'))
        <li class="breadcrumb-item"><a href="{{ route('cms.credits.index') }}">Credits</a></li>
    @endif
    <li class="breadcrumb-item active" aria-current="page">Credit History</li>
@endsection

@include('cms-kit::credits._styles')

@section('content')
@php
    $q = request()->query();
    $range = fn ($from, $to) => route('cms.credit-history.index', array_merge($q, ['from' => $from, 'to' => $to, 'page' => null]));
    $icons = ['topup' => 'fa-shopping-cart', 'grant' => 'fa-gift', 'spend' => 'fa-arrow-up', 'refund' => 'fa-undo', 'expire' => 'fa-hourglass-end'];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="cr-title fs-5"><i class="fas fa-history"></i> Credit history</div>
        <small class="text-muted">Every purchase, spend, refund and expiry, across all accounts. <span class="text-success fw-bold">+</span> = credits in, − = credits used.</small>
    </div>
    @if($cmsUser->can('credits.view'))
        <a href="{{ route('cms.credits.index') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-cog me-1"></i> Credit settings</a>
    @endif
</div>

<div class="row g-2 mb-3">
    @foreach([
        [$filtersActive ? 'Entries (filtered)' : 'Entries', number_format($totals->cnt), 'fa-list', 'linear-gradient(135deg, #264373, #3a5794)'],
        ['Credits in', '+' . number_format($totals->credits_in), 'fa-arrow-down', 'linear-gradient(135deg, #0f9d58, #34c880)'],
        ['Credits used / expired', '−' . number_format($totals->credits_out), 'fa-arrow-up', 'linear-gradient(135deg, #e08e0b, #f5a623)'],
        ['Money received', 'AED ' . number_format($totals->money, 2), 'fa-coins', 'linear-gradient(135deg, #04a1cc, #2fc4e8)'],
        ['Accounts', number_format($totals->accounts), 'fa-users', 'linear-gradient(135deg, #6d28d9, #8b5cf6)'],
    ] as [$label, $value, $icon, $bg])
    <div class="col-6 col-lg">
        <div class="cr-stat">
            <span class="cr-stat-icon" style="background: {{ $bg }};"><i class="fas {{ $icon }}"></i></span>
            <div><div class="cr-stat-value">{{ $value }}</div><div class="cr-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

<form method="GET" class="cr-panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Agent, email, listing reference, note">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">What happened</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Spent on</label>
            <select name="kind" class="form-select form-select-sm">
                <option value="">Anything</option>
                <option value="listing" @selected(request('kind') === 'listing')>Extra listing</option>
                <option value="premium" @selected(request('kind') === 'premium')>Premium</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Account</label>
            <select name="account_type" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="agent" @selected(request('account_type') === 'agent')>Agents</option>
                <option value="company" @selected(request('account_type') === 'company')>Companies</option>
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
        <div class="col-6 col-md-2 d-flex gap-1">
            <button class="btn btn-primary btn-sm flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
            @if($filtersActive)
            <a href="{{ route('cms.credit-history.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear filters"><i class="fas fa-times"></i></a>
            @endif
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <div class="cr-quick d-flex gap-1 flex-wrap">
            <a href="{{ $range(now()->toDateString(), now()->toDateString()) }}">Today</a>
            <a href="{{ $range(now()->subDays(6)->toDateString(), now()->toDateString()) }}">Last 7 days</a>
            <a href="{{ $range(now()->startOfMonth()->toDateString(), now()->toDateString()) }}">This month</a>
            <a href="{{ $range(now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}">Last month</a>
            <a href="{{ $range(now()->subDays(90)->toDateString(), now()->toDateString()) }}">Last 90 days</a>
            <a href="{{ $range(now()->startOfYear()->toDateString(), now()->toDateString()) }}">This year</a>
        </div>
        <a href="{{ route('cms.credit-history.export', request()->query()) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV{{ $filtersActive ? ' (filtered)' : '' }}</a>
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
                        <th>What happened</th>
                        <th>Done by</th>
                        <th class="text-end">Credits</th>
                        <th class="text-end">Balance after</th>
                        <th>Listing</th>
                        <th class="text-end pe-4">Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    @php
                        $u = $t->account;
                        $label = $u?->displayName() ?? 'Deleted account';
                        $initials = strtoupper(collect(preg_split('/\s+/', trim($label)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';
                    @endphp
                    <tr>
                        <td class="ps-4 text-nowrap">
                            <div class="fw-semibold">{{ $t->created_at->format('d M Y') }}</div>
                            <div class="text-muted small">{{ $t->created_at->format('h:i A') }}</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="cr-avatar" style="background: {{ $u?->type === 'company' ? 'linear-gradient(135deg,#264373,#3a5794)' : 'linear-gradient(135deg,#04a1cc,#2fc4e8)' }};">{{ $initials }}</span>
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
                            <span class="cr-type cr-type--{{ $t->type }}"><i class="fas {{ $icons[$t->type] ?? 'fa-circle' }}"></i> {{ $types[$t->type] ?? ucfirst($t->type) }}</span>
                            <div class="small mt-1">{{ $t->description }}</div>
                        </td>
                        <td class="small">{{ $t->actor_name ?: 'System' }}</td>
                        <td class="text-end cr-credits {{ $t->credits > 0 ? 'text-success' : '' }}">{{ $t->credits > 0 ? '+' : '' }}{{ number_format($t->credits) }}</td>
                        <td class="text-end">{{ number_format($t->balance_after) }}</td>
                        <td class="small">{{ $t->reference ?: '—' }}</td>
                        <td class="text-end pe-4 small text-nowrap">{{ $t->total !== null ? 'AED ' . number_format($t->total, 2) : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="fas fa-history fa-2x mb-2 d-block opacity-50"></i>
                            {{ $filtersActive ? 'No credit activity matches these filters.' : 'No credit activity yet.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $transactions->links('pagination::bootstrap-5') }}</div>
@endsection
