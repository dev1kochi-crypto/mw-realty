@extends('portal.layouts.app')

@section('title', $agent->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="portal-section-title mb-0">{{ $agent->name }}</div>
    <a href="{{ route('portal.agents.index') }}" class="portal-btn-ghost btn btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="portal-card p-4 h-100">
            <div class="mb-2"><span class="badge bg-{{ $membership->statusTone() }}-subtle text-{{ $membership->statusTone() }}-emphasis border">{{ $membership->statusLabel() }}</span></div>
            <div class="small portal-muted">Agent ID</div><div class="mb-2">{{ $agent->id }}</div>
            <div class="small portal-muted">Email</div><div class="mb-2">{{ $agent->email }}</div>
            <div class="small portal-muted">Phone</div><div class="mb-2">{{ $agent->phone ?: '—' }}</div>
            <div class="small portal-muted">Joined</div><div class="mb-2">{{ $membership->joined_at?->format('d M Y') ?? '—' }}</div>
            <div class="small portal-muted">Agency listings assigned</div><div class="mb-2">{{ $properties->total() }}</div>
            <div class="small portal-muted">Agency leads assigned (all time)</div><div class="mb-3">{{ $leadCount }}</div>
            <a href="{{ route('portal.crm.leads.index', ['agent_id' => $agent->id]) }}" class="btn btn-sm portal-btn-ghost">View their leads</a>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card p-4 mb-3">
            <div class="fw-bold mb-2">Assigned agency listings</div>
            <table class="table portal-table mb-0">
                <thead><tr><th>Ref</th><th>Title</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($properties as $property)
                    <tr>
                        <td>{{ $property->reference_no }}</td>
                        <td><a href="{{ route('portal.properties.edit', $property->id) }}" class="text-decoration-none">{{ $property->getTranslation('title') ?? '—' }}</a></td>
                        <td>{{ $property->status ? 'Active' : 'Inactive' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="portal-empty">No listings assigned.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-2">{{ $properties->links() }}</div>
        </div>

        <div class="portal-card p-4 mb-3">
            <div class="fw-bold mb-2">Membership history with your agency</div>
            <ul class="list-unstyled small mb-0">
                @foreach($history as $row)
                <li class="mb-1">
                    <span class="badge bg-{{ $row->statusTone() }}-subtle text-{{ $row->statusTone() }}-emphasis border">{{ $row->statusLabel() }}</span>
                    {{ ucfirst($row->initiated_by) }}-initiated · {{ $row->created_at->format('d M Y') }}
                    @if($row->joined_at) · joined {{ $row->joined_at->format('d M Y') }}@endif
                    @if($row->left_at) · left {{ $row->left_at->format('d M Y') }}@endif
                </li>
                @endforeach
            </ul>
        </div>

        @if(in_array($membership->status, \App\Models\AgencyAgent::MEMBER_STATUSES, true))
        <div class="portal-card p-4 border border-danger-subtle" id="remove">
            <div class="fw-bold mb-1 text-danger">Remove from agency</div>
            <div class="portal-muted small mb-3">
                Their account, personal listings and lead history are kept. Leads already assigned to them stay on record — reassign them from Leads if needed.
                Choose who takes over the {{ $properties->total() }} agency listing(s) assigned to them, or leave them unassigned so new enquiries round-robin.
            </div>
            <form method="POST" action="{{ route('portal.agents.remove', $membership->id) }}" class="d-flex flex-wrap gap-2" onsubmit="return confirm('Remove {{ e($agent->name) }} from your agency?');">
                @csrf
                <select name="reassign_to" class="form-select" style="max-width: 300px;">
                    <option value="">Leave listings unassigned (round-robin)</option>
                    @foreach($eligibleAgents as $other)
                    <option value="{{ $other->id }}">Reassign listings to {{ $other->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-danger">Remove agent</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
