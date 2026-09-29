@extends('cms-kit::layouts.cms')

@php
    $tabs = [
        'pending' => ['label' => 'Awaiting approval', 'icon' => 'fa-hourglass-half', 'tone' => 'amber', 'hint' => 'Needs your decision'],
        'active' => ['label' => 'Active', 'icon' => 'fa-user-check', 'tone' => 'green', 'hint' => 'Working in an agency'],
        'suspended' => ['label' => 'Suspended', 'icon' => 'fa-user-lock', 'tone' => 'slate', 'hint' => 'Paused by agency / admin'],
        'awaiting' => ['label' => 'Invited / Requested', 'icon' => 'fa-paper-plane', 'tone' => 'blue', 'hint' => 'Waiting on the other side'],
        'closed' => ['label' => 'Closed', 'icon' => 'fa-box-archive', 'tone' => 'rose', 'hint' => 'Left, rejected or declined'],
    ];
    $initials = fn (?string $name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
    $how = fn ($m) => match ($m->initiated_by) {
        'agency' => $m->account_created_by_agency ? ['Added by agency', 'fa-user-plus'] : ['Invited by agency', 'fa-envelope-open-text'],
        'agent' => ['Agent requested', 'fa-hand'],
        default => ['Assigned by admin', 'fa-user-shield'],
    };
@endphp

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.portal-accounts.index') }}" class="text-decoration-none text-muted">Clients</a></li>
    <li class="breadcrumb-item active" aria-current="page">Agency Agents</li>
@endsection

@push('styles')
<style>
    .aa-head h4 { font-weight: 800; letter-spacing: -0.01em; }
    .min-w-0 { min-width: 0; }
    .aa-tiles { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.9rem; }
    @media (max-width: 1199.98px) { .aa-tiles { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .aa-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .aa-tile {
        display: flex; align-items: center; gap: 0.8rem; padding: 0.95rem 1rem; border-radius: 16px; background: #fff;
        border: 1.5px solid #eceef4; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s, transform .15s;
    }
    .aa-tile:hover { border-color: #d5dbe8; box-shadow: 0 8px 22px rgba(28, 35, 64, 0.07); transform: translateY(-1px); color: inherit; }
    .aa-tile.is-active { border-color: var(--primary-color, #04a1cc); box-shadow: 0 8px 22px rgba(4, 161, 204, 0.16); }
    .aa-tile__icon { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .aa-tile__count { font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
    .aa-tile__label { font-size: 0.8rem; font-weight: 700; color: #3b4157; }
    .aa-tile__hint { font-size: 0.7rem; color: #8a90a6; }
    .tone-amber { background: rgba(245, 158, 11, 0.12); color: #d97706; }
    .tone-green { background: rgba(22, 163, 74, 0.12); color: #16a34a; }
    .tone-slate { background: rgba(100, 116, 139, 0.13); color: #475569; }
    .tone-blue { background: rgba(4, 161, 204, 0.12); color: #0487ab; }
    .tone-rose { background: rgba(220, 38, 38, 0.1); color: #dc2626; }
    .aa-tile.is-urgent .aa-tile__count { color: #d97706; }

    .aa-card { border-radius: 18px; }
    .aa-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #f0f1f6; }
    .aa-search { position: relative; min-width: 280px; }
    .aa-search i { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #9aa0b4; font-size: 0.8rem; }
    .aa-search input { padding-left: 2.2rem; border-radius: 10px; border: 1.5px solid #e1e4ec; height: 38px; font-size: 0.85rem; }
    .aa-search input:focus { border-color: var(--primary-color, #04a1cc); box-shadow: 0 0 0 3px rgba(4, 161, 204, 0.12); }

    .aa-table thead th { white-space: nowrap; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: #838aa3; background: #f9fafc; border-bottom: 1px solid #eef0f5; padding: 0.75rem 1rem; font-weight: 700; }
    .aa-table tbody td { vertical-align: middle; font-size: 0.85rem; padding: 0.85rem 1rem; border-color: #f2f3f7; }
    .aa-table tbody tr:hover { background: #fafbfd; }
    .aa-avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.75rem; color: #fff; background: linear-gradient(135deg, var(--primary-color, #04a1cc), #2fc4e8); }
    .aa-avatar--agency { border-radius: 10px; background: linear-gradient(135deg, var(--secondary-color, #264373), #3a5794); width: 32px; height: 32px; font-size: 0.68rem; }
    .aa-pill { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.74rem; font-weight: 600; color: #4b5065; background: #f3f4f8; border-radius: 999px; padding: 0.25rem 0.65rem; white-space: nowrap; }
    .aa-status { font-size: 0.72rem; font-weight: 700; border-radius: 999px; padding: 0.3rem 0.7rem; }
    .aa-actions { display: inline-flex; gap: 0.4rem; flex-wrap: wrap; justify-content: flex-end; }
    .aa-actions .btn { font-size: 0.78rem; font-weight: 700; border-radius: 9px; padding: 0.35rem 0.8rem; }
    .aa-empty { padding: 3.5rem 1rem; text-align: center; color: #8a90a6; }
    .aa-empty i { font-size: 2rem; color: #cfd4e2; margin-bottom: 0.75rem; }
    .aa-footer { padding: 0.85rem 1.25rem; border-top: 1px solid #f0f1f6; }
    .aa-footer .pagination { margin: 0; }
</style>
@endpush

@section('content')
<div class="aa-head d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h4 class="mb-1">Agency Agents</h4>
        <div class="text-muted small">Agents an agency added or invited, and agents who asked to join. Approve them before they become active in the agency.</div>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
@endif

<div class="aa-tiles mb-3">
    @foreach($tabs as $key => $tab)
    <a href="{{ route('cms.agency-agents.index', array_filter(['status' => $key, 'q' => $search ?: null])) }}"
       class="aa-tile {{ $filter === $key ? 'is-active' : '' }} {{ $key === 'pending' && $filterCounts[$key] ? 'is-urgent' : '' }}">
        <span class="aa-tile__icon tone-{{ $tab['tone'] }}"><i class="fas {{ $tab['icon'] }}"></i></span>
        <span class="min-w-0">
            <span class="aa-tile__count d-block">{{ number_format($filterCounts[$key]) }}</span>
            <span class="aa-tile__label d-block text-truncate">{{ $tab['label'] }}</span>
            <span class="aa-tile__hint d-block text-truncate">{{ $tab['hint'] }}</span>
        </span>
    </a>
    @endforeach
</div>

<div class="card aa-card border-0 shadow-sm">
    <div class="aa-toolbar">
        <div>
            <div class="fw-bold">{{ $tabs[$filter]['label'] }}</div>
            <div class="text-muted small">
                {{ number_format($memberships->total()) }} {{ \Illuminate\Support\Str::plural('membership', $memberships->total()) }}@if($search !== '') matching &ldquo;{{ $search }}&rdquo;@endif
            </div>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="status" value="{{ $filter }}">
            <div class="aa-search">
                <i class="fas fa-search"></i>
                <input type="search" name="q" value="{{ $search }}" class="form-control" maxlength="100" placeholder="Search agent or agency name / email">
            </div>
            <button class="btn btn-sm btn-primary px-3" style="height: 38px; border-radius: 10px;">Search</button>
            @if($search !== '')<a href="{{ route('cms.agency-agents.index', ['status' => $filter]) }}" class="btn btn-sm btn-light" style="height: 38px; border-radius: 10px; line-height: 26px;">Clear</a>@endif
        </form>
    </div>

    @if($memberships->isEmpty())
    <div class="aa-empty">
        <div><i class="fas {{ $tabs[$filter]['icon'] }}"></i></div>
        <div class="fw-semibold text-dark mb-1">{{ $search !== '' ? 'No matches' : 'Nothing here' }}</div>
        <div class="small">
            @if($search !== '')
                No {{ strtolower($tabs[$filter]['label']) }} memberships match &ldquo;{{ $search }}&rdquo;.
            @elseif($filter === 'pending')
                You're all caught up — no agents are waiting for approval.
            @else
                No {{ strtolower($tabs[$filter]['label']) }} memberships yet.
            @endif
        </div>
    </div>
    @else
    <div class="table-responsive">
        <table class="table aa-table mb-0">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Agency</th>
                    <th>How</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($memberships as $membership)
                @php [$howLabel, $howIcon] = $how($membership); @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="aa-avatar">{{ $initials($membership->agent->name) }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('cms.portal-accounts.show', ['id' => $membership->agent_id, 'type' => 'agent']) }}" class="fw-semibold text-decoration-none text-dark d-block text-truncate">{{ $membership->agent->name }}</a>
                                <div class="text-muted small text-truncate">{{ $membership->agent->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="aa-avatar aa-avatar--agency">{{ $initials($membership->agency->displayName()) }}</span>
                            <a href="{{ route('cms.portal-accounts.show', ['id' => $membership->agency_id, 'type' => 'company']) }}" class="text-decoration-none text-dark fw-semibold text-truncate">{{ $membership->agency->displayName() }}</a>
                        </div>
                    </td>
                    <td>
                        <span class="aa-pill"><i class="fas {{ $howIcon }}"></i>{{ $howLabel }}</span>
                        @if($membership->account_created_by_agency)<div class="small text-muted mt-1">New account</div>@endif
                    </td>
                    <td>
                        <span class="aa-status bg-{{ $membership->statusTone() }}-subtle text-{{ $membership->statusTone() }}-emphasis">{{ $membership->statusLabel() }}</span>
                        @if($membership->rejection_reason)<div class="small text-muted mt-1 text-truncate" style="max-width: 220px;" title="{{ $membership->rejection_reason }}">{{ $membership->rejection_reason }}</div>@endif
                    </td>
                    <td class="small text-nowrap">
                        <div>{{ $membership->updated_at->format('d M Y') }}</div>
                        <div class="text-muted">{{ $membership->updated_at->diffForHumans() }}</div>
                    </td>
                    <td class="text-end">
                        @can('portal-accounts.edit')
                        <div class="aa-actions">
                            @if($membership->status === 'pending')
                                <form method="POST" action="{{ route('cms.agency-agents.approve', $membership->id) }}">@csrf
                                    <button class="btn btn-success"><i class="fas fa-check me-1"></i>Approve</button>
                                </form>
                                <button type="button" class="btn btn-outline-danger aa-reject" data-action="{{ route('cms.agency-agents.reject', $membership->id) }}" data-name="{{ $membership->agent->name }}" data-agency="{{ $membership->agency->displayName() }}"><i class="fas fa-xmark me-1"></i>Reject</button>
                            @elseif($membership->status === 'approved')
                                <form method="POST" action="{{ route('cms.agency-agents.suspend', $membership->id) }}">@csrf
                                    <button class="btn btn-outline-secondary"><i class="fas fa-pause me-1"></i>Suspend</button>
                                </form>
                            @elseif($membership->status === 'suspended')
                                <form method="POST" action="{{ route('cms.agency-agents.reactivate', $membership->id) }}">@csrf
                                    <button class="btn btn-outline-success"><i class="fas fa-play me-1"></i>Reactivate</button>
                                </form>
                            @endif
                            @if(in_array($membership->status, ['approved', 'suspended'], true))
                                <form method="POST" action="{{ route('cms.agency-agents.remove', $membership->id) }}" onsubmit="return confirm('Remove {{ addslashes($membership->agent->name) }} from {{ addslashes($membership->agency->displayName()) }}? Their account and history are kept.');">@csrf
                                    <button class="btn btn-outline-danger"><i class="fas fa-user-minus me-1"></i>Remove</button>
                                </form>
                            @endif
                            @if(!in_array($membership->status, ['pending', 'approved', 'suspended'], true))
                                <span class="text-muted small">—</span>
                            @endif
                        </div>
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($memberships->hasPages())
    <div class="aa-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">Showing {{ $memberships->firstItem() }}–{{ $memberships->lastItem() }} of {{ number_format($memberships->total()) }}</span>
        {{ $memberships->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
    @endif
    @endif
</div>

{{-- Reject popup (optional reason) --}}
<div class="modal fade" id="aaRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0" style="border-radius: 18px;" id="aaRejectForm">
            @csrf
            <div class="modal-body p-4 text-center">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; border-radius: 50%; background: rgba(220,53,69,.1); color: #dc3545; font-size: 1.35rem;"><i class="fas fa-user-xmark"></i></div>
                <h5 class="fw-bold mb-1">Reject membership?</h5>
                <p class="text-muted small mb-3" id="aaRejectText"></p>
                <textarea name="reason" class="form-control" rows="3" maxlength="500" placeholder="Reason (optional) — shown to the agency and agent"></textarea>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4 justify-content-center gap-2">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-4"><i class="fas fa-xmark me-1"></i>Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.aa-reject');
        if (!btn) return;
        const form = document.getElementById('aaRejectForm');
        form.action = btn.dataset.action;
        form.reset();
        document.getElementById('aaRejectText').textContent = btn.dataset.name + ' will not join ' + btn.dataset.agency + '.';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('aaRejectModal')).show();
    });
</script>
@endpush
