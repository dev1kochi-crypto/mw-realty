@extends('cms-kit::layouts.cms')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Support Tickets</li>
@endsection

@include('cms-kit::support-tickets._styles')

@section('content')
<div class="row g-2 mb-3">
    @foreach([
        ['Needs Reply', $stats['needs_reply'], 'fa-inbox', 'linear-gradient(135deg, #dc3545, #ef6a76)', 'needs_reply'],
        ['Open Tickets', $stats['active'], 'fa-folder-open', 'linear-gradient(135deg, #04a1cc, #2fc4e8)', 'active'],
        ['Awaiting Client', $stats['awaiting'], 'fa-hourglass-half', 'linear-gradient(135deg, #e08e0b, #f5a623)', 'awaiting_client'],
        ['Solved / Closed', $stats['solved'], 'fa-check-circle', 'linear-gradient(135deg, #0f9d58, #34c880)', 'resolved'],
    ] as [$label, $value, $icon, $bg, $filter])
    <div class="col-6 col-md-3">
        <a href="{{ route('cms.support-tickets.index', ['status' => $filter]) }}" class="tk-stat text-decoration-none">
            <span class="tk-stat-icon" style="background: {{ $bg }};"><i class="fas {{ $icon }}"></i></span>
            <div><div class="tk-stat-value">{{ $value }}</div><div class="tk-stat-label">{{ $label }}</div></div>
        </a>
    </div>
    @endforeach
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0">Support Tickets</h5>
                <span class="text-muted small">Raised by Agents &amp; Companies from the portal's Contact Us page.</span>
            </div>
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="active" @selected($status === 'active')>All open</option>
                    <option value="needs_reply" @selected($status === 'needs_reply')>Needs reply</option>
                    @foreach(\App\Models\SupportTicket::STATUSES as $key => [$label])
                    <option value="{{ $key }}" @selected($status === $key)>{{ $key === 'awaiting_client' ? 'Awaiting Client' : $label }}</option>
                    @endforeach
                    <option value="all" @selected($status === 'all')>All tickets</option>
                </select>
                <select name="category" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All issue types</option>
                    @foreach(\App\Models\SupportTicket::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="priority" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="">Any priority</option>
                    @foreach(\App\Models\SupportTicket::PRIORITIES as $key => $label)
                    <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" style="width: 220px;" placeholder="Ticket no., subject, client">
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Ticket</th>
                        <th>Client</th>
                        <th>Issue Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Last Update</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    @php $needsReply = in_array($ticket->status, ['open', 'in_progress'], true) && $ticket->last_reply_by === 'client'; @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="tk-ref">{{ $ticket->reference() }}</span>
                            @if($needsReply)<span class="badge bg-danger ms-1">Needs reply</span>@endif
                            <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($ticket->subject, 60) }}</div>
                        </td>
                        <td class="small">
                            <div class="fw-semibold">{{ $ticket->portalUser?->displayName() ?? '—' }}</div>
                            <div class="text-muted">{{ $ticket->portalUser?->type === 'company' ? 'Company' : 'Agent' }} &middot; {{ $ticket->portalUser?->email }}</div>
                        </td>
                        <td class="small">{{ $ticket->categoryLabel() }}</td>
                        <td><span class="tk-prio tk-prio--{{ $ticket->priority }}">{{ $ticket->priorityLabel() }}</span></td>
                        <td><span class="badge tk-status--{{ $ticket->statusTone() }}">{{ $ticket->statusLabel(true) }}</span></td>
                        <td class="small text-muted text-nowrap">
                            {{ ($ticket->last_reply_at ?? $ticket->updated_at)->diffForHumans() }}
                            <div>by {{ $ticket->last_reply_by === 'admin' ? 'Support' : 'Client' }}</div>
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('cms.support-tickets.show', $ticket) }}" class="btn btn-sm btn-outline-primary" title="Open"><i class="fas fa-comments me-1"></i>Open</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-headset fa-2x mb-2 d-block opacity-50"></i>
                            No tickets match these filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $tickets->links('pagination::bootstrap-5') }}</div>
@endsection
