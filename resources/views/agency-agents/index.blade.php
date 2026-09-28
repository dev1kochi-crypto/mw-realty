@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('cms.portal-accounts.index') }}" class="text-decoration-none text-muted">Clients</a></li>
    <li class="breadcrumb-item active" aria-current="page">Agency Agents</li>
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0 fw-bold">Agency Agents</h4>
        <small class="text-muted">Agents an agency added or invited, and agents who asked to join — approve before they become active in the agency.</small>
    </div>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="status" value="{{ $filter }}">
        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Agent or agency name / email" style="min-width: 240px;">
        <button class="btn btn-sm btn-outline-primary">Search</button>
    </form>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<ul class="nav nav-tabs mb-3">
    @foreach(['pending' => 'Awaiting approval', 'active' => 'Active', 'suspended' => 'Suspended', 'awaiting' => 'Invited / Requested', 'closed' => 'Closed'] as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="{{ route('cms.agency-agents.index', ['status' => $key, 'q' => $search ?: null]) }}">
            {{ $label }} <span class="badge {{ $key === 'pending' && $filterCounts[$key] ? 'bg-danger' : 'bg-secondary' }}">{{ $filterCounts[$key] }}</span>
        </a>
    </li>
    @endforeach
</ul>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Agency</th>
                        <th>How</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($memberships as $membership)
                    <tr>
                        <td>
                            <a href="{{ route('cms.portal-accounts.show', ['id' => $membership->agent_id, 'type' => 'agent']) }}" class="fw-semibold text-decoration-none">{{ $membership->agent->name }}</a>
                            <div class="text-muted small">{{ $membership->agent->email }}</div>
                            @if($membership->account_created_by_agency)
                            <span class="badge bg-info-subtle text-info-emphasis border">New account (created by agency)</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('cms.portal-accounts.show', ['id' => $membership->agency_id, 'type' => 'company']) }}" class="text-decoration-none">{{ $membership->agency->displayName() }}</a>
                        </td>
                        <td class="small">
                            {{ match ($membership->initiated_by) { 'agency' => $membership->account_created_by_agency ? 'Added by agency' : 'Invited by agency', 'agent' => 'Agent requested', default => 'Assigned by admin' } }}
                        </td>
                        <td>
                            <span class="badge bg-{{ $membership->statusTone() }}-subtle text-{{ $membership->statusTone() }}-emphasis border">{{ $membership->statusLabel() }}</span>
                            @if($membership->rejection_reason)<div class="small text-muted">{{ $membership->rejection_reason }}</div>@endif
                        </td>
                        <td class="small">{{ $membership->updated_at->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            @can('portal-accounts.edit')
                                @if($membership->status === 'pending')
                                    <form method="POST" action="{{ route('cms.agency-agents.approve', $membership->id) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-{{ $membership->id }}">Reject</button>
                                    <form method="POST" action="{{ route('cms.agency-agents.reject', $membership->id) }}" class="collapse mt-2" id="reject-{{ $membership->id }}">@csrf
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="reason" class="form-control" placeholder="Reason (optional)" maxlength="500">
                                            <button class="btn btn-danger">Confirm</button>
                                        </div>
                                    </form>
                                @elseif($membership->status === 'approved')
                                    <form method="POST" action="{{ route('cms.agency-agents.suspend', $membership->id) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-outline-secondary">Suspend</button>
                                    </form>
                                @elseif($membership->status === 'suspended')
                                    <form method="POST" action="{{ route('cms.agency-agents.reactivate', $membership->id) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-outline-success">Reactivate</button>
                                    </form>
                                @endif
                                @if(in_array($membership->status, ['approved', 'suspended'], true))
                                    <form method="POST" action="{{ route('cms.agency-agents.remove', $membership->id) }}" class="d-inline" onsubmit="return confirm('Remove this agent from the agency? Their account and history are kept.');">@csrf
                                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Nothing here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $memberships->links() }}</div>
    </div>
</div>
@endsection
