@extends('cms-kit::layouts.cms')

@php
    $heading = $type === 'agent' ? 'Agents' : ($type === 'company' ? 'Agencies' : 'Agencies & Agents');
    $single = $type === 'agent' ? 'agent' : ($type === 'company' ? 'agency' : 'account');
    $addLabel = $type === 'agent' ? 'Add Agent' : ($type === 'company' ? 'Add Agency' : 'Add Agency / Agent');
    $showCompanyColumn = $type !== 'company';
    $showAgentsColumn = $type !== 'agent';
    $intro = match ($type) {
        'company' => 'Real estate agencies (brokerages) that list properties and manage their own team of agents.',
        'agent' => 'Brokers — independent, or working under an agency whose plan covers them.',
        default => 'Every agency and agent account on the platform.',
    };
@endphp

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">{{ $heading }}</li>
@endsection

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .pa { --pa-navy: var(--secondary-color, #264373); --pa-teal: var(--primary-color, #04a1cc); --pa-ink: #1c2340; --pa-muted: #838aa3; --pa-soft: #f7f8fc; --pa-border: rgba(28, 35, 64, .08); }
    .pa-hero { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.1rem; }
    .pa-hero h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--pa-ink); margin: 0; }
    .pa-hero p { color: var(--pa-muted); font-size: .86rem; margin: .2rem 0 0; }
    .pa-switch { display: inline-flex; gap: .25rem; padding: .3rem; border-radius: 14px; background: #fff; border: 1px solid var(--pa-border); box-shadow: 0 4px 14px rgba(28, 35, 64, .05); }
    .pa-switch a { display: inline-flex; align-items: center; gap: .45rem; padding: .5rem 1rem; border-radius: 10px; font-weight: 700; font-size: .86rem; color: var(--pa-muted); text-decoration: none; transition: all .2s; }
    .pa-switch a span { padding: 0 .45rem; border-radius: 99px; font-size: .72rem; background: var(--pa-soft); }
    .pa-switch a:hover { color: var(--pa-navy); }
    .pa-switch a.is-active { color: #fff; background: linear-gradient(135deg, var(--pa-navy), #3a5794); box-shadow: 0 6px 16px rgba(38, 67, 115, .25); }
    .pa-switch a.is-active span { background: rgba(255, 255, 255, .2); }

    .pa-tiles { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .85rem; margin-bottom: 1.1rem; }
    @media (max-width: 1199.98px) { .pa-tiles { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .pa-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pa-tile { display: flex; align-items: center; gap: .8rem; padding: .95rem 1.05rem; border-radius: 16px; background: #fff; border: 1px solid var(--pa-border); box-shadow: 0 4px 14px rgba(28, 35, 64, .05); text-align: left; transition: transform .2s, box-shadow .2s, border-color .2s; }
    button.pa-tile:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(28, 35, 64, .1); }
    .pa-tile.is-alert { border-color: rgba(224, 142, 11, .5); background: linear-gradient(135deg, #fff, #fff8eb); }
    .pa-tile__icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; }
    .pa-tile__value { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.35rem; line-height: 1.1; color: var(--pa-ink); }
    .pa-tile__label { font-size: .72rem; font-weight: 700; color: var(--pa-muted); }
    .fill-teal { background: linear-gradient(135deg, var(--pa-teal), #2fc4e8); }
    .fill-navy { background: linear-gradient(135deg, var(--pa-navy), #3a5794); }
    .fill-green { background: linear-gradient(135deg, #0f9d58, #34c880); }
    .fill-amber { background: linear-gradient(135deg, #e08e0b, #f5a623); }
    .fill-slate { background: linear-gradient(135deg, #5b6478, #8891a8); }

    .pa-card { background: #fff; border: 1px solid var(--pa-border); border-radius: 18px; box-shadow: 0 6px 20px rgba(28, 35, 64, .05); }
    .pa-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--pa-border); }
    .pa-pills { display: flex; flex-wrap: wrap; gap: .4rem; }
    .pa-pill { border: 1px solid var(--pa-border); background: #fff; border-radius: 99px; padding: .32rem .85rem; font-size: .8rem; font-weight: 700; color: var(--pa-muted); transition: all .2s; }
    .pa-pill span { margin-left: .25rem; padding: 0 .4rem; border-radius: 99px; background: var(--pa-soft); font-size: .72rem; }
    .pa-pill:hover { color: var(--pa-navy); border-color: rgba(38, 67, 115, .35); }
    .pa-pill.is-active { background: var(--pa-navy); border-color: var(--pa-navy); color: #fff; }
    .pa-pill.is-active span { background: rgba(255, 255, 255, .2); }
    .pa-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .pa-length { display: inline-flex; align-items: center; gap: .4rem; margin: 0; font-size: .82rem; font-weight: 700; color: var(--pa-muted); }
    .pa-length select { width: auto; border-radius: 10px; font-size: .85rem; }
    .pa-search { position: relative; }
    .pa-search i { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); font-size: .78rem; color: var(--pa-muted); }
    .pa-search input { padding-left: 2.1rem; width: 250px; border-radius: 10px; font-size: .85rem; }
    .btn-brand-add { background: linear-gradient(135deg, var(--pa-teal), var(--pa-navy)); border: none; color: #fff; font-weight: 700; font-size: .85rem; padding: .5rem 1.1rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(4, 161, 204, .25); }
    .btn-brand-add:hover { color: #fff; opacity: .92; }
    .btn-toolbar-outline { border: 1.5px solid #e1e4ec; color: #4b5065; font-weight: 700; font-size: .85rem; padding: .45rem .95rem; border-radius: 10px; background: #fff; }
    .btn-toolbar-outline:hover { background: var(--pa-soft); color: var(--pa-navy); }
    .btn-toolbar-outline.has-filters { border-color: var(--pa-teal); color: var(--pa-teal); }

    .pa-filters-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; margin: .9rem 1.25rem 0; padding: .55rem .9rem; border-radius: 12px; background: #eaf7fb; border: 1px solid #b8e6f2; }

    .pa-table-wrap { padding: .25rem .75rem .75rem; }
    .premium-table { border-collapse: separate; border-spacing: 0; }
    .premium-table thead th { white-space: nowrap; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--pa-muted); background: transparent; border-bottom: 1.5px solid var(--pa-border); padding: .85rem .6rem; }
    .premium-table tbody td { vertical-align: middle; font-size: .84rem; padding: .85rem .6rem; border-bottom: 1px solid #f1f2f7; }
    .premium-table tbody tr { transition: background .15s; }
    .premium-table tbody tr:hover { background: #fafbfe; }
    .premium-table tbody tr:has(.row-checkbox:checked) { background: #f0f9fc; }
    .premium-table td.pa-num { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--pa-ink); }
    .premium-table td.pa-num a { color: var(--pa-teal); }
    .contact-avatar { width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .78rem; color: #fff; box-shadow: 0 4px 10px rgba(28, 35, 64, .12); }
    .contact-subline { font-size: .74rem; line-height: 1.5; }
    .min-w-0 { min-width: 0; }
    .editable-cell .cell-edit { display: flex; }
    .editable-cell .cell-edit.d-none { display: none; }
    .editable-cell .cell-display { display: inline-flex; align-items: center; }
    .edit-cell-btn { opacity: .35; transition: opacity .15s; }
    tr:hover .edit-cell-btn { opacity: 1; }
    .dataTables_wrapper .dataTables_info { font-size: .8rem; color: var(--pa-muted); padding-top: 1rem !important; }
    .dataTables_wrapper .dataTables_length { font-size: .8rem; color: var(--pa-muted); padding-top: .8rem; }
    .dataTables_wrapper .dataTables_paginate { padding-top: .8rem !important; }
    .dataTables_wrapper .page-link { border-radius: 8px !important; margin: 0 .12rem; border: 1px solid var(--pa-border); font-size: .8rem; font-weight: 700; color: var(--pa-navy); }
    .dataTables_wrapper .page-item.active .page-link { background: var(--pa-navy); border-color: var(--pa-navy); color: #fff; }

    /* Floating bar while rows are ticked. */
    .pa-selection { position: fixed; left: 50%; bottom: 1.4rem; z-index: 1040; transform: translate(-50%, 160%); display: flex; align-items: center; gap: .9rem; padding: .65rem .7rem .65rem 1.1rem;
        border-radius: 16px; background: #1c2340; color: #fff; box-shadow: 0 18px 40px rgba(28, 35, 64, .35); transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
    .pa-selection.is-open { transform: translate(-50%, 0); }
    .pa-selection strong { font-size: .88rem; }
    .pa-selection .btn { font-weight: 700; font-size: .82rem; border-radius: 10px; }
    .pa-selection .btn:disabled { opacity: .45; }

    .reject-modal-content { border: none; border-radius: 20px; box-shadow: 0 24px 60px rgba(28, 35, 64, .22); }
    .reject-modal-icon { width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 1.1rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: rgba(220, 53, 69, .1); color: #dc3545; }
    .reject-modal-icon.tone-success { background: rgba(25, 135, 84, .1); color: #198754; }
    .pa-note { display: flex; gap: .6rem; align-items: flex-start; font-size: .8rem; color: #4b5065; padding: .7rem 1.25rem; background: var(--pa-soft); border-radius: 0 0 18px 18px; border-top: 1px solid var(--pa-border); }
    .pa-note i { color: var(--pa-teal); margin-top: .15rem; }
</style>
@endpush

@section('content')
<div class="pa">
    <div class="pa-hero">
        <div>
            <h3>{{ $heading }}</h3>
            <p>{{ $intro }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <nav class="pa-switch" aria-label="Account type">
                <a href="{{ route('cms.portal-accounts.index', ['type' => 'company']) }}" class="{{ $type === 'company' ? 'is-active' : '' }}"><i class="fas fa-building"></i> Agencies <span>{{ $typeCounts['company'] ?? 0 }}</span></a>
                <a href="{{ route('cms.portal-accounts.index', ['type' => 'agent']) }}" class="{{ $type === 'agent' ? 'is-active' : '' }}"><i class="fas fa-user-tie"></i> Agents <span>{{ $typeCounts['agent'] ?? 0 }}</span></a>
                <a href="{{ route('cms.portal-accounts.index') }}" class="{{ !$type ? 'is-active' : '' }}">All</a>
            </nav>
            @can('portal-accounts.edit')
            <a href="{{ route('cms.portal-accounts.create', array_filter(['type' => $type])) }}" class="btn btn-brand-add"><i class="fas fa-plus me-1"></i> {{ $addLabel }}</a>
            @endcan
        </div>
    </div>

    <div class="pa-tiles">
        <button type="button" class="pa-tile pa-quick" data-status="">
            <span class="pa-tile__icon {{ $type === 'agent' ? 'fill-teal' : 'fill-navy' }}"><i class="fas {{ $type === 'agent' ? 'fa-user-tie' : 'fa-building' }}"></i></span>
            <div><div class="pa-tile__value">{{ number_format($counts['all']) }}</div><div class="pa-tile__label">Total {{ $type ? strtolower($heading) : 'accounts' }}</div></div>
        </button>
        <button type="button" class="pa-tile pa-quick {{ $counts['pending'] ? 'is-alert' : '' }}" data-status="pending">
            <span class="pa-tile__icon fill-amber"><i class="fas fa-hourglass-half"></i></span>
            <div><div class="pa-tile__value">{{ number_format($counts['pending']) }}</div><div class="pa-tile__label">Waiting for approval</div></div>
        </button>
        <button type="button" class="pa-tile pa-quick" data-status="approved">
            <span class="pa-tile__icon fill-green"><i class="fas fa-circle-check"></i></span>
            <div><div class="pa-tile__value">{{ number_format($counts['approved']) }}</div><div class="pa-tile__label">Approved</div></div>
        </button>
        <div class="pa-tile">
            <span class="pa-tile__icon fill-teal"><i class="fas fa-credit-card"></i></span>
            <div><div class="pa-tile__value">{{ number_format($counts['paid']) }}</div><div class="pa-tile__label">Paid plans</div></div>
        </div>
        <div class="pa-tile">
            <span class="pa-tile__icon fill-slate"><i class="fas fa-user-plus"></i></span>
            <div><div class="pa-tile__value">{{ number_format($counts['new_30']) }}</div><div class="pa-tile__label">Joined · 30 days</div></div>
        </div>
    </div>

    <div class="pa-card">
        <div class="pa-toolbar">
            <div class="pa-pills" role="tablist" aria-label="Approval">
                <button type="button" class="pa-pill pa-quick is-active" data-status="">All <span>{{ $counts['all'] }}</span></button>
                <button type="button" class="pa-pill pa-quick" data-status="pending">Waiting for approval <span>{{ $counts['pending'] }}</span></button>
                <button type="button" class="pa-pill pa-quick" data-status="approved">Approved <span>{{ $counts['approved'] }}</span></button>
                <button type="button" class="pa-pill pa-quick" data-status="rejected">Rejected <span>{{ $counts['rejected'] }}</span></button>
            </div>
            <div class="pa-actions">
                <label class="pa-length">Show
                    <select id="paPageLength" class="form-select form-select-sm" aria-label="Rows per page">
                        @foreach([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="pa-search">
                    <i class="fas fa-search"></i>
                    <input type="search" id="paSearch" class="form-control form-control-sm" placeholder="Name, email or phone…">
                </div>
                <button type="button" class="btn btn-toolbar-outline" id="openFiltersBtn" data-bs-toggle="offcanvas" data-bs-target="#filtersPanel">
                    <i class="fas fa-sliders me-1"></i> Filters
                    <span id="filterCountBadge" class="badge rounded-pill bg-primary ms-1 d-none">0</span>
                </button>
                <a href="#" id="exportBtn" class="btn btn-toolbar-outline"><i class="fas fa-file-excel me-1"></i> Export</a>
            </div>
        </div>

        <div id="activeFiltersBar" class="pa-filters-bar d-none">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <i class="fas fa-filter text-primary"></i>
                <span class="fw-bold small text-nowrap">Filters applied:</span>
                <div id="activeFilterChips" class="d-flex flex-wrap gap-2"></div>
            </div>
            <button type="button" id="clearFiltersBtn" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="fas fa-times me-1"></i>Clear all</button>
        </div>

        <div class="pa-table-wrap table-responsive">
            <table class="table premium-table mb-0 w-100">
                <thead>
                    <tr>
                        <th style="width: 36px;"><input type="checkbox" id="selectAllRows" class="form-check-input"></th>
                        <th style="width: 40px;">#</th>
                        <th style="min-width: 230px;">{{ $type === 'agent' ? 'Agent' : ($type === 'company' ? 'Agency' : 'Account') }}</th>
                        @if(!$type)
                        <th>Type</th>
                        @endif
                        @if($showCompanyColumn)
                        <th>Agency</th>
                        @endif
                        @if($showAgentsColumn)
                        <th>Agents</th>
                        @endif
                        <th>Listings</th>
                        <th>Leads</th>
                        <th>Plan</th>
                        <th>Payment</th>
                        <th>Approval</th>
                        <th class="text-center">Login</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="pa-note">
            <i class="fas fa-circle-info"></i>
            <div>Agencies and agents can sign up themselves at <code>/portal/register</code>, or you can add them with <strong>{{ $addLabel }}</strong>. Self-registered accounts wait for your approval — only approved accounts can sign in and list properties. <strong>Login</strong> switches an account's access off without deleting it.</div>
        </div>
    </div>
</div>

<!-- Floating bulk actions while rows are ticked -->
<div class="pa-selection" id="paSelection" role="region" aria-label="Selected accounts">
    <strong><span id="paSelectedCount">0</span> selected</strong>
    <!-- Shown once the whole page is ticked: widen to every matching account on every page. -->
    <button type="button" id="paSelectAllMatching" class="btn btn-sm btn-outline-light d-none">Select all <span>0</span></button>
    @can('portal-accounts.edit')
    <button type="button" id="bulkApproveBtn" class="btn btn-success btn-sm" disabled><i class="fas fa-check me-1"></i> Approve (<span id="bulkApproveCount">0</span>)</button>
    @endcan
    @can('portal-accounts.delete')
    <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm" disabled><i class="fas fa-trash me-1"></i> Delete (<span id="bulkDeleteCount">0</span>)</button>
    @endcan
    <button type="button" id="paClearSelection" class="btn btn-sm btn-outline-light"><i class="fas fa-xmark"></i></button>
</div>

<!-- Filters offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="filtersPanel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold">Filter {{ strtolower($heading) }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3">
        @if($showCompanyColumn)
        <div>
            <label class="form-label fw-bold">Agency</label>
            <select id="filterCompany" class="form-select form-select-sm">
                <option value="">Any</option>
                @foreach($companiesForFilter as $company)
                <option value="{{ $company->id }}">{{ $company->company_name ?: $company->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="form-label fw-bold">Listings</label>
            <select id="filterHasProperties" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="1">Has listings</option>
                <option value="0">No listings</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Leads</label>
            <select id="filterHasLeads" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="1">Has leads</option>
                <option value="0">No leads</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Plan</label>
            <select id="filterPlan" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="none">No Plan</option>
                @foreach($plansForFilter as $plan)
                <option value="{{ $plan->id }}">{{ $plan->getTranslation('name') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Approval</label>
            <select id="filterStatus" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="pending">Waiting for approval</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Waiting for</label>
            <select id="filterPendingDays" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="3">3+ days</option>
                <option value="7">7+ days</option>
                <option value="14">14+ days</option>
                <option value="30">30+ days</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Payment</label>
            <select id="filterPaymentStatus" class="form-select form-select-sm">
                <option value="">Any</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
            </select>
        </div>
        <div>
            <label class="form-label fw-bold">Paid in month <span class="text-muted fw-normal">(who paid that month)</span></label>
            <input type="month" id="filterPaymentMonth" class="form-control form-control-sm">
        </div>
        <div class="row g-2">
            <div class="col-6">
                <label class="form-label fw-bold">Joined from</label>
                <input type="date" id="filterJoinedFrom" class="form-control form-control-sm">
            </div>
            <div class="col-6">
                <label class="form-label fw-bold">Joined to</label>
                <input type="date" id="filterJoinedTo" class="form-control form-control-sm">
            </div>
        </div>

        <div class="d-flex gap-2 mt-auto pt-2">
            <button type="button" id="applyFiltersBtn" class="btn btn-brand-add flex-grow-1">Apply filters</button>
            <button type="button" id="resetFiltersBtn" class="btn btn-outline-secondary">Reset</button>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content reject-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="reject-modal-icon"><i class="fas fa-ban"></i></div>
                <h5 class="fw-bold mb-2">Reject this account?</h5>
                <p class="text-muted mb-3" style="font-size: 0.85rem;">
                    Optionally add a reason — it will be included in the email sent to them.
                </p>
                <textarea id="rejectReasonInput" class="form-control" rows="3" placeholder="Reason for rejecting (optional)"></textarea>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="rejectReasonConfirmBtn">Reject Account</button>
            </div>
        </div>
    </div>
</div>

{{-- Bulk Delete / Approve confirm popup — replaces the browser's confirm() --}}
<div class="modal fade" id="bulkConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content reject-modal-content">
            <div class="modal-body text-center p-4 pb-2">
                <div class="reject-modal-icon" id="bulkConfirmIcon"></div>
                <h5 class="fw-bold mb-2" id="bulkConfirmTitle"></h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;" id="bulkConfirmMessage"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn px-4" id="bulkConfirmBtn"></button>
            </div>
        </div>
    </div>
</div>

@include('portal-accounts.partials.reset-password-modal')
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(function() {
        const baseAjaxUrl = "{{ route('cms.portal-accounts.index', array_filter(['type' => $type])) }}";
        const exportUrlBase = "{{ route('cms.portal-accounts.export', array_filter(['type' => $type])) }}";

        function currentFilters() {
            const filters = {};
            const map = {
                filterCompany: 'company_id',
                filterHasProperties: 'has_properties',
                filterHasLeads: 'has_leads',
                filterPlan: 'plan_id',
                filterStatus: 'status',
                filterPendingDays: 'pending_days',
                filterPaymentStatus: 'payment_status',
                filterPaymentMonth: 'payment_month',
                filterJoinedFrom: 'joined_from',
                filterJoinedTo: 'joined_to',
            };
            Object.entries(map).forEach(([elId, param]) => {
                const el = document.getElementById(elId);
                if (el && el.value) filters[param] = el.value;
            });
            return filters;
        }

        function buildUrl(base, extraParams) {
            const url = new URL(base, window.location.origin);
            Object.entries(extraParams).forEach(([k, v]) => url.searchParams.set(k, v));
            return url.toString();
        }

        // An applied filter set stays visible on the page (badge count + a chip row with a one-click Clear).
        const FILTER_LABELS = {
            company_id: 'Agency',
            has_properties: 'Listings',
            has_leads: 'Leads',
            plan_id: 'Plan',
            status: 'Approval',
            pending_days: 'Waiting for',
            payment_status: 'Payment',
            payment_month: 'Paid in month',
            joined_from: 'Joined from',
            joined_to: 'Joined to',
        };

        function renderActiveFiltersBar(filters) {
            const entries = Object.entries(filters);
            const badge = $('#filterCountBadge');
            const bar = $('#activeFiltersBar');
            const chips = $('#activeFilterChips');

            // The approval pills / tiles reflect the status filter.
            $('.pa-quick').removeClass('is-active');
            $(`.pa-pill.pa-quick[data-status="${filters.status || ''}"]`).addClass('is-active');

            if (entries.length === 0) {
                badge.addClass('d-none');
                bar.addClass('d-none');
                chips.empty();
                return;
            }

            chips.empty();
            entries.forEach(([param, value]) => {
                const elId = filterParamMap[param];
                const el = elId ? document.getElementById(elId) : null;
                let displayValue = value;
                if (el && el.tagName === 'SELECT') {
                    const opt = Array.from(el.options).find(o => o.value === value);
                    if (opt) displayValue = opt.text;
                }
                const label = FILTER_LABELS[param] || param;
                const safeValue = $('<div>').text(displayValue).html();
                chips.append(`<span class="badge bg-white text-dark border fw-normal">${label}: ${safeValue}</span>`);
            });

            badge.removeClass('d-none').text(entries.length);
            bar.removeClass('d-none');
        }

        // Deep links (e.g. from a Plan's page: ?plan_id=3) arrive pre-filtered.
        const filterParamMap = {
            company_id: 'filterCompany',
            has_properties: 'filterHasProperties',
            has_leads: 'filterHasLeads',
            plan_id: 'filterPlan',
            status: 'filterStatus',
            pending_days: 'filterPendingDays',
            payment_status: 'filterPaymentStatus',
            payment_month: 'filterPaymentMonth',
            joined_from: 'filterJoinedFrom',
            joined_to: 'filterJoinedTo',
        };
        const urlParams = new URLSearchParams(window.location.search);
        const initialFilters = {};
        Object.entries(filterParamMap).forEach(([param, elId]) => {
            const value = urlParams.get(param);
            const el = document.getElementById(elId);
            if (value && el) {
                el.value = value;
                initialFilters[param] = value;
            }
        });
        if (Object.keys(initialFilters).length > 0) {
            $('#openFiltersBtn').addClass('has-filters');
        }
        renderActiveFiltersBar(initialFilters);

        // Selection: the ticked rows on this page, or "all matching" — every account the current
        // type / filters / search match, on every page, minus the ones unticked afterwards.
        let allMatching = false;
        let excludedIds = new Set();

        const table = $('.premium-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: buildUrl(baseAjaxUrl, initialFilters),
            // Own search box, page size and toolbar above; table, then info / pages below.
            dom: 'rt<"d-flex flex-wrap justify-content-between align-items-center gap-2 px-2"i p>',
            pageLength: Number($('#paPageLength').val()),
            language: { lengthMenu: 'Show _MENU_', info: '_START_–_END_ of _TOTAL_', infoEmpty: 'Nothing here', zeroRecords: 'No accounts match.' },
            columns: [
                {data: 'select_all', name: 'select_all', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'contact', name: 'contact', orderable: false},
                @if(!$type)
                {data: 'type', name: 'type', searchable: false},
                @endif
                @if($showCompanyColumn)
                {data: 'company', name: 'company', orderable: false, searchable: false},
                @endif
                @if($showAgentsColumn)
                {data: 'agents_count', name: 'agents_count', orderable: false, searchable: false, className: 'pa-num'},
                @endif
                {data: 'properties_count', name: 'properties_count', searchable: false, className: 'pa-num'},
                {data: 'leads_count', name: 'leads_count', searchable: false, className: 'pa-num'},
                {data: 'plan', name: 'plan', orderable: false, searchable: false},
                {data: 'payment_status', name: 'payment_status', orderable: false, searchable: false},
                {data: 'status', name: 'status', orderable: false, searchable: false},
                {data: 'is_active', name: 'is_active', orderable: false, searchable: false, className: 'text-center'},
                {data: 'joined', name: 'joined', searchable: false},
                {data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end'},
            ],
            // Newest first by default, on the Joined column so the header shows the active sort.
            order: [[{{ 9 + (int) !$type + (int) $showCompanyColumn + (int) $showAgentsColumn }}, 'desc']],
            drawCallback: function () {
                // In "all matching" mode every row on the new page starts ticked (minus ones unticked).
                if (allMatching) {
                    $('.row-checkbox').each(function () { this.checked = !excludedIds.has(this.value); });
                }
                updateBulkButtons();
            }
        });

        $('#paPageLength').on('change', function () {
            table.page.len(Number(this.value)).draw();
        });

        let searchTimer = null;
        $('#paSearch').on('input', function () {
            clearTimeout(searchTimer);
            const value = this.value;
            searchTimer = setTimeout(() => { clearSelection(); table.search(value).draw(); }, 300);
        });

        const base = "{{ url(config('cms-kit.common.auth.prefix', 'admin')) }}/portal-accounts/";

        function selectedCount() {
            return allMatching
                ? Math.max(0, table.page.info().recordsDisplay - excludedIds.size)
                : $('.row-checkbox:checked').length;
        }

        function updateBulkButtons() {
            const count = selectedCount();
            const rows = $('.row-checkbox');
            const ticked = rows.filter(':checked').length;
            const pageAll = rows.length > 0 && ticked === rows.length;
            $('#selectAllRows').prop('checked', pageAll).prop('indeterminate', ticked > 0 && !pageAll);

            $('#bulkDeleteCount, #bulkApproveCount, #paSelectedCount').text(count);
            $('#bulkDeleteBtn, #bulkApproveBtn').prop('disabled', count === 0);
            $('#paSelection').toggleClass('is-open', count > 0);

            const total = table.page.info().recordsDisplay;
            $('#paSelectAllMatching').toggleClass('d-none', allMatching || !pageAll || total <= rows.length)
                .find('span').text(total);
        }

        function clearSelection() {
            allMatching = false;
            excludedIds = new Set();
            $('.row-checkbox, #selectAllRows').prop('checked', false);
            updateBulkButtons();
        }

        // Header checkbox: the rows on this page.
        $('#selectAllRows').on('change', function () {
            const checked = $(this).is(':checked');
            $('.row-checkbox').each(function () {
                this.checked = checked;
                if (allMatching) checked ? excludedIds.delete(this.value) : excludedIds.add(this.value);
            });
            updateBulkButtons();
        });
        $('#paSelectAllMatching').on('click', function () {
            allMatching = true;
            excludedIds = new Set();
            $('.row-checkbox').prop('checked', true);
            updateBulkButtons();
        });
        $('#paClearSelection').on('click', clearSelection);

        $(document).on('change', '.row-checkbox', function () {
            if (allMatching) this.checked ? excludedIds.delete(this.value) : excludedIds.add(this.value);
            updateBulkButtons();
        });

        /** Body for bulk-delete / bulk-approve: the ticked ids, or the list's own type / filters / search. */
        function selectionPayload() {
            if (!allMatching) {
                return { ids: $('.row-checkbox:checked').map(function () { return $(this).val(); }).get() };
            }
            return {
                ...currentFilters(),
                @if($type) type: @json($type), @endif
                select_all: 1,
                exclude_ids: [...excludedIds],
                search: table.search(),
            };
        }

        function runBulk(endpoint) {
            $.post(base + endpoint, { _token: '{{ csrf_token() }}', ...selectionPayload() })
                .done(() => { clearSelection(); table.ajax.reload(null, false); });
        }

        const bulkModalEl = document.getElementById('bulkConfirmModal');
        const bulkModal = new bootstrap.Modal(bulkModalEl);
        let bulkEndpoint = null;

        function confirmBulk(opts) {
            $('#bulkConfirmIcon').attr('class', 'reject-modal-icon ' + (opts.tone || '')).html('<i class="fas ' + opts.icon + '"></i>');
            $('#bulkConfirmTitle').text(opts.title);
            $('#bulkConfirmMessage').text(opts.message);
            $('#bulkConfirmBtn').attr('class', 'btn px-4 ' + opts.buttonClass).text(opts.button).prop('disabled', false);
            bulkEndpoint = opts.endpoint;
            bulkModal.show();
        }

        $('#bulkConfirmBtn').on('click', function () {
            if (!bulkEndpoint) return;
            $(this).prop('disabled', true);
            runBulk(bulkEndpoint);
            bulkEndpoint = null;
            bulkModal.hide();
        });

        $('#bulkDeleteBtn').on('click', function () {
            const count = selectedCount();
            if (!count) return;
            confirmBulk({
                endpoint: 'bulk-delete', icon: 'fa-trash', buttonClass: 'btn-danger', button: 'Delete (' + count + ')',
                title: 'Delete ' + count + ' account(s)?',
                message: 'They will be deleted permanently. Their properties and CRM leads will be removed too.',
            });
        });

        $('#bulkApproveBtn').on('click', function () {
            const count = selectedCount();
            if (!count) return;
            confirmBulk({
                endpoint: 'bulk-approve', tone: 'tone-success', icon: 'fa-check', buttonClass: 'btn-success', button: 'Approve (' + count + ')',
                title: 'Approve ' + count + ' account(s)?',
                message: 'Each account will get an approval email.',
            });
        });

        // Inline edit toggling — shared by the Plan, Status and Payment columns
        $(document).on('click', '.edit-cell-btn', function () {
            const cell = $(this).closest('.editable-cell');
            cell.find('.cell-display').addClass('d-none');
            cell.find('.cell-edit').removeClass('d-none');
        });

        $(document).on('click', '.cancel-cell-btn', function () {
            const cell = $(this).closest('.editable-cell');
            cell.find('.cell-edit').addClass('d-none');
            cell.find('.cell-display').removeClass('d-none');
        });

        $(document).on('click', '.submit-plan-btn', function () {
            const cell = $(this).closest('.editable-cell');
            const id = cell.data('id');
            const planId = cell.find('.plan-select-inline').val();
            $.post(base + id + '/assign-plan', { _token: '{{ csrf_token() }}', plan_id: planId })
                .done(() => table.ajax.reload(null, false));
        });

        function submitStatus(id, status, reason) {
            return $.post(base + id + '/status', { _token: '{{ csrf_token() }}', status: status, reason: reason })
                .done(() => table.ajax.reload(null, false));
        }

        // Custom reject-reason modal — replaces the native browser prompt() popup
        const rejectModalEl = document.getElementById('rejectReasonModal');
        const rejectModal = rejectModalEl ? new bootstrap.Modal(rejectModalEl) : null;
        let pendingRejectId = null;

        $(document).on('click', '.submit-status-btn', function () {
            const cell = $(this).closest('.editable-cell');
            const id = cell.data('id');
            const status = cell.find('.status-select-inline').val();

            if (status === 'rejected') {
                pendingRejectId = id;
                document.getElementById('rejectReasonInput').value = '';
                rejectModal.show();
                return;
            }

            submitStatus(id, status, null);
        });

        $('#rejectReasonConfirmBtn').on('click', function () {
            if (!pendingRejectId) return;
            const reason = document.getElementById('rejectReasonInput').value;
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';

            submitStatus(pendingRejectId, 'rejected', reason)
                .always(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    rejectModal.hide();
                    pendingRejectId = null;
                });
        });

        $(document).on('click', '.submit-payment-btn', function () {
            const cell = $(this).closest('.editable-cell');
            const id = cell.data('id');
            const paymentStatus = cell.find('.payment-select-inline').val();
            $.post(base + id + '/payment-status', { _token: '{{ csrf_token() }}', payment_status: paymentStatus })
                .done(() => table.ajax.reload(null, false));
        });

        $(document).on('change', '.toggle-active', function () {
            const id = $(this).data('id');
            $.post(base + id + '/toggle-active', { _token: '{{ csrf_token() }}' })
                .fail(() => table.ajax.reload(null, false));
        });

        function applyFilters() {
            const filters = currentFilters();
            clearSelection();
            table.ajax.url(buildUrl(baseAjaxUrl, filters)).load();
            $('#openFiltersBtn').toggleClass('has-filters', Object.keys(filters).length > 0);
            renderActiveFiltersBar(filters);
        }

        // Approval pills and tiles: quick status filter (same as the panel's Approval field).
        $(document).on('click', '.pa-quick', function () {
            document.getElementById('filterStatus').value = $(this).data('status') || '';
            applyFilters();
        });

        // Filters
        $('#applyFiltersBtn').on('click', function () {
            applyFilters();
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('filtersPanel')).hide();
        });

        $('#resetFiltersBtn').on('click', function () {
            document.querySelectorAll('#filtersPanel select, #filtersPanel input').forEach(el => el.value = '');
            clearSelection();
            table.ajax.url(baseAjaxUrl).load();
            $('#openFiltersBtn').removeClass('has-filters');
            renderActiveFiltersBar({});
        });

        // Same reset, reachable directly from the page without opening the filters panel first.
        $('#clearFiltersBtn').on('click', function () {
            $('#resetFiltersBtn').trigger('click');
        });

        $('#exportBtn').on('click', function (e) {
            e.preventDefault();
            window.location.href = buildUrl(exportUrlBase, currentFilters());
        });
    });
</script>
@endpush
