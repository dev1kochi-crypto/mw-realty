@extends('portal.crm._layout')

@section('title', 'Website Leads')

{{--
    Website Leads (Super Admin) — same layout as All Leads: toolbar, filter bar, selectable table.
    Tick leads (or "select all matching") and Transfer them to an agency / agent (_transfer_modal).
--}}
@php
    $tabIcons = ['pool' => 'fa-inbox', 'routed' => 'fa-share', 'all' => 'fa-users'];
    $tabHints = [
        'pool' => 'Visitors who shared their details (AI chat, contact forms, customer accounts) but haven\'t opened a listing that belongs to an agency or agent yet — transfer them to one.',
        'routed' => 'Handed to an agency / agent — automatically when they viewed one of its listings, or transferred by you.',
        'all' => 'Everyone who identified themselves on the website, with their tracked activity.',
    ];
    $activeFilterCount = collect([$search !== '', (bool) $source])->filter()->count();
@endphp

@push('styles')
<style>
    .wl-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
    .wl-tab { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: #fff; border: 1px solid #e8eaf2; border-radius: 999px; color: #3b4064; font-size: 13px; font-weight: 600; text-decoration: none; }
    .wl-tab:hover { border-color: #c9cde0; color: #1f2340; }
    .wl-tab.is-active { background: #b9233c; border-color: #b9233c; color: #fff; }
    .wl-tab-count { font-size: 11.5px; padding: 1px 8px; border-radius: 999px; background: rgba(0, 0, 0, .06); }
    .wl-tab.is-active .wl-tab-count { background: rgba(255, 255, 255, .22); }
    .wl-pill { display: inline-block; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #eef7f0; color: #1d7a3a; margin: 1px 4px 1px 0; white-space: nowrap; }
    .wl-pill.is-pool { background: #fff4e0; color: #a35a00; }
    .wl-badge { font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: #e6f0ff; color: #244373; }
    .wl-select-all { font-size: 13px; background: #f4f5fa; border-radius: 10px; padding: 8px 12px; }
    .wl-row { cursor: pointer; }
</style>
@endpush

@section('crm-content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-3">
            <div class="portal-section-title mb-0">Website Leads</div>
            <span class="portal-selected-badge d-none" id="wlSelectedBadge">Selected Leads <span id="wlSelectedCount">0</span></span>
        </div>
        <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 760px;">{{ $tabHints[$status] }}</p>
    </div>
    <div class="portal-xbtn-bar">
        <button type="button" id="wlBulkTransfer" data-needs-selection class="portal-xbtn portal-xbtn-primary" aria-label="Transfer selected leads" title="Select leads first" disabled><i class="fas fa-share"></i><span>Transfer</span></button>
    </div>
</div>

@if(session('success'))
<script>document.addEventListener('DOMContentLoaded', function () { window.portalToast('success', @json(session('success'))); });</script>
@endif
@if($errors->any())
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<nav class="wl-tabs mb-3" aria-label="Website lead status">
    @foreach($statuses as $key => $label)
    <a href="{{ route('portal.crm.website-leads.index', ['status' => $key]) }}" class="wl-tab {{ $status === $key ? 'is-active' : '' }}" @if($status === $key) aria-current="page" @endif>
        <i class="fas {{ $tabIcons[$key] }}"></i>{{ $label }}<span class="wl-tab-count">{{ number_format($counts[$key]) }}</span>
    </a>
    @endforeach
</nav>

<div class="portal-card portal-filter-bar mb-3">
    <form method="GET" class="portal-filter-bar__form">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="portal-filter-field portal-filter-field--search {{ $search !== '' ? 'is-set' : '' }}">
            <label class="visually-hidden" for="wlFilterSearch">Search</label>
            <div class="portal-filter-control">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="wlFilterSearch" name="q" value="{{ $search }}" class="form-control" placeholder="Name, email or phone" autocomplete="off" maxlength="100">
            </div>
        </div>
        <div class="portal-filter-field {{ $source ? 'is-set' : '' }}">
            <label class="visually-hidden" for="wlFilterSource">Came in via</label>
            <div class="portal-filter-control">
                <i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>
                <select id="wlFilterSource" name="source" class="form-select" onchange="this.form.submit()">
                    <option value="">All sources</option>
                    @foreach(\App\Models\Visitors\VisitorLead::SOURCE_LABELS as $key => $label)
                    <option value="{{ $key }}" @selected($source === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="portal-filter-actions">
            <button type="submit" class="portal-filter-submit" title="Apply filters" aria-label="Apply filters"><i class="fas fa-filter" aria-hidden="true"></i></button>
            @if($activeFilterCount)
            <a href="{{ route('portal.crm.website-leads.index', ['status' => $status]) }}" class="portal-filter-bar__clear" title="Clear all filters"><i class="fas fa-xmark" aria-hidden="true"></i> Clear <span class="portal-filter-bar__badge">{{ $activeFilterCount }}</span></a>
            @endif
        </div>
    </form>
</div>

<div class="portal-card p-3 p-md-4">
    <div class="wl-select-all d-none mb-3" id="wlSelectAllBar">
        <span id="wlSelectAllText"></span>
        <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" id="wlSelectAllToggle"></button>
    </div>

    <div class="table-responsive">
        <table class="table portal-table mb-0">
            <thead>
                <tr>
                    <th style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="wlSelectPage" aria-label="Select all on this page" @disabled($leads->isEmpty())></th>
                    <th>Lead</th>
                    <th>Phone</th>
                    <th>Came in via</th>
                    <th>Insights</th>
                    <th>Routed to</th>
                    <th>Last active</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr class="portal-lead-row wl-row" data-href="{{ route('portal.crm.website-leads.show', $lead) }}" tabindex="0" aria-label="View {{ $lead->displayName() }}">
                    <td data-lead-selection><input type="checkbox" class="form-check-input wl-select" value="{{ $lead->id }}" aria-label="Select {{ $lead->displayName() }}"></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="portal-lead-avatar">{{ strtoupper(mb_substr($lead->displayName(), 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-1 flex-nowrap" style="max-width: 220px;">
                                    <a href="{{ route('portal.crm.website-leads.show', $lead) }}" class="portal-lead-name-button text-decoration-none text-truncate" title="{{ $lead->displayName() }}">{{ $lead->displayName() }}</a>
                                    @if($lead->user_id)<span class="wl-badge flex-shrink-0" title="Has a customer account">Customer</span>@endif
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 220px;">{{ $lead->email ?: '-' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $lead->formattedPhone() ?: '-' }}</td>
                    <td>{{ $lead->sourceLabel() }}</td>
                    <td>
                        <a href="{{ route('portal.crm.website-leads.show', $lead) }}#insights" class="portal-lead-insights" title="Property views · time on site · AI chats">
                            <span><i class="fas fa-house" aria-hidden="true"></i>{{ $lead->property_views_count }}</span>
                            <span><i class="far fa-clock" aria-hidden="true"></i>{{ \App\Services\Visitors\VisitorInsights::duration((int) $lead->site_seconds) }}</span>
                            @if($lead->chats_count)<span><i class="fas fa-robot" aria-hidden="true"></i>{{ $lead->chats_count }}</span>@endif
                        </a>
                    </td>
                    <td>
                        @forelse($lead->crmLeads as $crmLead)
                            <span class="wl-pill">{{ $crmLead->owner?->displayName() }}</span>
                        @empty
                            <span class="wl-pill is-pool">In pool</span>
                        @endforelse
                    </td>
                    <td class="text-muted text-nowrap">{{ $lead->last_seen_at?->diffForHumans() ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="portal-empty text-center text-muted py-5">
                        <i class="fas fa-user-clock fa-2x mb-2 d-block opacity-50"></i>
                        {{ $activeFilterCount ? 'No website leads match these filters.' : 'No website leads here yet.' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leads->hasPages())
    <div class="mt-3">{{ $leads->links() }}</div>
    @endif
</div>

@include('portal.crm.website-leads._transfer_modal')
@endsection

@push('scripts')
<script>
// Selection: ticked rows on this page, or "all matching" (every page, minus unticked ones).
(function () {
    const total = {{ $leads->total() }};
    const limit = {{ $bulkLimit }};
    const selected = new Set();
    const excluded = new Set();
    let allMode = false;
    const boxes = () => Array.from(document.querySelectorAll('.wl-select'));
    const pageBox = document.getElementById('wlSelectPage');
    const bar = document.getElementById('wlSelectAllBar');

    function count() { return allMode ? Math.min(total - excluded.size, limit) : selected.size; }

    function refresh() {
        const n = count();
        document.getElementById('wlSelectedBadge').classList.toggle('d-none', n === 0);
        document.getElementById('wlSelectedCount').textContent = n;
        const transfer = document.getElementById('wlBulkTransfer');
        transfer.disabled = n === 0;
        transfer.title = n ? 'Transfer ' + n + ' lead' + (n === 1 ? '' : 's') : 'Select leads first';
        const ticked = boxes().filter((b) => b.checked).length;
        pageBox.checked = ticked > 0 && ticked === boxes().length;
        pageBox.indeterminate = ticked > 0 && ticked < boxes().length;

        // Offer "select all matching" once the whole page is ticked and there's more beyond it.
        const showBar = allMode || (pageBox.checked && total > boxes().length);
        bar.classList.toggle('d-none', !showBar);
        if (showBar) {
            document.getElementById('wlSelectAllText').textContent = allMode
                ? 'All ' + n + ' matching leads are selected' + (total > limit ? ' (up to ' + limit + ' per transfer).' : '.')
                : 'All ' + boxes().length + ' leads on this page are selected.';
            document.getElementById('wlSelectAllToggle').textContent = allMode ? 'Clear selection' : 'Select all ' + total + ' matching leads';
        }
    }

    document.addEventListener('change', (e) => {
        if (e.target === pageBox) {
            boxes().forEach((b) => {
                b.checked = pageBox.checked;
                allMode ? (b.checked ? excluded.delete(b.value) : excluded.add(b.value)) : (b.checked ? selected.add(b.value) : selected.delete(b.value));
            });
        } else if (e.target.classList.contains('wl-select')) {
            const id = e.target.value;
            allMode ? (e.target.checked ? excluded.delete(id) : excluded.add(id)) : (e.target.checked ? selected.add(id) : selected.delete(id));
        } else {
            return;
        }
        refresh();
    });

    document.getElementById('wlSelectAllToggle').addEventListener('click', () => {
        allMode = !allMode;
        selected.clear();
        excluded.clear();
        boxes().forEach((b) => { b.checked = allMode; });
        refresh();
    });

    document.getElementById('wlBulkTransfer').addEventListener('click', () => {
        window.openWebsiteLeadTransfer(allMode
            ? { all: true, exclude: Array.from(excluded), count: count() }
            : { ids: Array.from(selected), count: selected.size });
    });

    // Whole row opens the lead (like All Leads) — except the checkbox and links.
    document.querySelectorAll('.wl-row').forEach((row) => {
        const open = (e) => {
            if (e.target.closest('a, button, input, label, [data-lead-selection]')) return;
            window.location = row.dataset.href;
        };
        row.addEventListener('click', open);
        row.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(e); });
    });

    refresh();
})();
</script>
@endpush
